<?php

namespace App\Services\Fleet;

use App\Models\BusinessJob;
use App\Models\DevItem;
use App\Models\FieldAsset;
use App\Models\FieldAssetDocument;
use App\Models\FieldAssetInspection;
use App\Models\FieldAssetType;
use App\Models\FieldAssetWorkOrder;
use App\Models\FieldSyncLog;
use App\Models\Shipment;
use App\Models\ShipmentType;
use App\Models\User;
use App\Services\ImageSanitizer;
use App\Services\Shipping\ShipmentInspectionService;
use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Talks to the field app's database (contract: docs/fleet-field-sync-contract.md).
 *
 * ForgeDesk always initiates. The field side is untrusted input: every submission is
 * validated as if it came from the internet and anything odd is parked for review in
 * field_sync_log rather than applied.
 */
class FieldSync
{
    public const CACHE_LAST_OUT = 'fleet:sync:last_out';

    public const CACHE_LAST_IN = 'fleet:sync:last_in';

    private const SHIPMENT_PERMISSIONS = ['shipments.view', 'shipments.manage', 'shipments.inspect'];

    private const PHOTO_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(private FleetService $fleet, private ShipmentInspectionService $shipments) {}

    public function enabled(): bool
    {
        return (bool) config('fleet.sync.enabled');
    }

    private function remote(): Connection
    {
        return DB::connection(config('fleet.sync.connection'));
    }

    /** @return array{last_out: ?string, last_in: ?string, stale: bool, enabled: bool} */
    public function status(): array
    {
        $out = Cache::get(self::CACHE_LAST_OUT);
        $in = Cache::get(self::CACHE_LAST_IN);
        $limit = now()->subMinutes((int) config('fleet.sync.stale_after_minutes'));

        return [
            'enabled' => $this->enabled(),
            'last_out' => $out,
            'last_in' => $in,
            'stale' => $this->enabled() && (! $in || Carbon::parse($in)->lt($limit) || ! $out || Carbon::parse($out)->lt($limit)),
        ];
    }

    // ---- ForgeDesk -> field app ----------------------------------------------------------

    /**
     * Publish the minimal snapshot the crews need. Deliberately excludes costs, purchase
     * info, VIN/plate, documents and notes.
     *
     * @return array{assets: int, checklists: int, users: int, shipments: int, jobs: int, shipment_types: int}
     */
    public function publish(): array
    {
        $remote = $this->remote();
        $now = now()->toDateTimeString();

        $assets = FieldAsset::with(['type', 'job:id,job_number,job_name'])->inService()->get();
        $rows = $assets->map(fn ($a) => [
            'asset_number' => $a->asset_number,
            'name' => $a->name,
            'type_name' => $a->type->name,
            'meter_type' => $a->type->meter_type,
            'status' => $a->status,
            'current_meter' => $a->current_meter,
            'current_job_label' => $a->job ? trim(($a->job->job_name ?? '').' '.($a->job->job_number ? '('.$a->job->job_number.')' : '')) : $a->current_location_text,
            'is_active' => true,
            'synced_at' => $now,
        ])->all();

        $checklists = FieldAssetType::where('is_active', true)->get()->map(fn ($t) => [
            'type_name' => $t->name,
            'template_name' => $t->name.' inspection',
            'items' => json_encode(collect($t->default_checklist ?? [])->map(fn ($i) => ['label' => $i['label']])->values()),
            'updated_at' => $now,
        ])->all();

        $remote->transaction(function () use ($remote, $rows, $checklists) {
            foreach (array_chunk($rows, 200) as $chunk) {
                $remote->table('fa_assets')->upsert($chunk, ['asset_number'],
                    ['name', 'type_name', 'meter_type', 'status', 'current_meter', 'current_job_label', 'is_active', 'synced_at']);
            }
            // Assets no longer in service are hidden, never deleted (submissions may reference them).
            $remote->table('fa_assets')->whereNotIn('asset_number', array_column($rows, 'asset_number'))->update(['is_active' => false]);
            if ($checklists) {
                $remote->table('fa_checklists')->upsert($checklists, ['type_name'], ['template_name', 'items', 'updated_at']);
            }
        });

        // Assets are committed first; a field DB that predates fa_users (contract v2) then fails
        // loudly here instead of silently leaving crews unable to sign in.
        $users = $this->publishUsers($remote);
        $shipments = $this->publishShipments($remote);
        $jobs = $this->publishJobs($remote);
        $types = $this->publishShipmentTypes($remote);

        Cache::forever(self::CACHE_LAST_OUT, now()->toIso8601String());

        return ['assets' => count($rows), 'checklists' => count($checklists), 'users' => $users, 'shipments' => $shipments, 'jobs' => $jobs, 'shipment_types' => $types];
    }

    /**
     * Open shipments (contract v4): drafts and ready-to-ship ones for the phone's queue and pre-check, shipped
     * ones for the delivery check. Each carries both checklists resolved for its load types (item ids included;
     * `critical` is deliberately not published). Shipments that are delivered or cancelled are hidden, never deleted.
     */
    private function publishShipments(Connection $remote): int
    {
        $now = now()->toDateTimeString();
        $rows = Shipment::with(['jobs:id,job_number,job_name', 'loads.type:id,name', 'pickupLocation:id,name', 'dropoffLocation:id,name'])->whereIn('status', Shipment::OPEN_STATUSES)->get()
            ->map(fn (Shipment $s) => [
                'shipment_number' => $s->shipment_number,
                'job_label' => $s->jobLabel(),
                'destination' => $s->destination,
                'carrier' => $s->carrier,
                'pickup_name' => $s->pickupLocation?->name,
                'pickup_address' => $s->pickup_address,
                'pickup_lat' => $s->pickup_lat,
                'pickup_lng' => $s->pickup_lng,
                'dropoff_name' => $s->dropoffLocation?->name,
                'dropoff_address' => $s->dropoff_address,
                'dropoff_lat' => $s->dropoff_lat,
                'dropoff_lng' => $s->dropoff_lng,
                'ship_date' => $s->ship_date?->format('Y-m-d'),
                'status' => $s->status,
                'loads' => $s->loads->map(fn ($l) => $l->type->name.($l->piece_count ? ' x'.$l->piece_count : ''))->implode(', '),
                'pre_checklist' => json_encode($this->shipments->checklist($s, 'pre')),
                'checklist' => json_encode($this->shipments->checklist($s, 'post')),
                'is_active' => true,
                'synced_at' => $now,
            ])->all();

        $remote->transaction(function () use ($remote, $rows) {
            foreach (array_chunk($rows, 200) as $chunk) {
                $remote->table('fa_shipments')->upsert($chunk, ['shipment_number'],
                    ['job_label', 'destination', 'carrier', 'pickup_name', 'pickup_address', 'pickup_lat', 'pickup_lng', 'dropoff_name', 'dropoff_address', 'dropoff_lat', 'dropoff_lng', 'ship_date', 'status', 'loads', 'pre_checklist', 'checklist', 'is_active', 'synced_at']);
            }
            $remote->table('fa_shipments')->whereNotIn('shipment_number', array_column($rows, 'shipment_number'))->update(['is_active' => false]);
        });

        return count($rows);
    }

    /** Job number + name of jobs still in play, so the phone can tag a new shipment. Nothing else about a job leaves. */
    private function publishJobs(Connection $remote): int
    {
        $now = now()->toDateTimeString();
        $rows = BusinessJob::whereNotIn('status', ['completed', 'cancelled'])->orderByDesc('id')->limit(1000)
            ->get(['job_number', 'job_name'])->filter(fn ($j) => $j->job_number)
            ->map(fn ($j) => ['job_number' => Str::limit($j->job_number, 60, ''), 'job_name' => Str::limit((string) $j->job_name, 150, ''), 'is_active' => true, 'synced_at' => $now])
            ->unique('job_number')->values()->all();

        $remote->transaction(function () use ($remote, $rows) {
            foreach (array_chunk($rows, 200) as $chunk) {
                $remote->table('fa_jobs')->upsert($chunk, ['job_number'], ['job_name', 'is_active', 'synced_at']);
            }
            $remote->table('fa_jobs')->whereNotIn('job_number', array_column($rows, 'job_number'))->update(['is_active' => false]);
        });

        return count($rows);
    }

    private function publishShipmentTypes(Connection $remote): int
    {
        $now = now()->toDateTimeString();
        $rows = ShipmentType::orderBy('sort_order')->orderBy('name')->get()->map(fn ($t) => [
            'type_id' => $t->id, 'name' => $t->name, 'sort_order' => $t->sort_order, 'is_active' => (bool) $t->is_active, 'synced_at' => $now,
        ])->all();

        $remote->transaction(function () use ($remote, $rows) {
            foreach (array_chunk($rows, 200) as $chunk) {
                $remote->table('fa_shipment_types')->upsert($chunk, ['type_id'], ['name', 'sort_order', 'is_active', 'synced_at']);
            }
            $remote->table('fa_shipment_types')->whereNotIn('type_id', array_column($rows, 'type_id'))->update(['is_active' => false]);
        });

        return count($rows);
    }

    /**
     * Mobile-app logins (contract v2). Only users an admin ticked "Allow mobile app" for are ever
     * published, and a bcrypt hash is only on the field side while the account can actually sign in:
     * a user who is inactive, disabled, removed, has lost access, or still holds an unchanged
     * temporary password gets an empty hash, so nothing usable sits on the public-facing server.
     */
    private function publishUsers(Connection $remote): int
    {
        $now = now()->toDateTimeString();
        $users = User::where('mobile_app_access', true)->get();

        $rows = $users->map(function (User $u) use ($now) {
            $canSignIn = $u->is_active && ! $u->is_disabled && ! $u->must_change_password && $u->password;

            return [
                'forgedesk_user_id' => $u->id,
                'login' => mb_strtolower(trim($u->email)),
                'display_name' => Str::limit($u->full_name ?: $u->name, 100, ''),
                'password_hash' => $canSignIn ? $u->password : '',
                // Only the shipment permissions are published, to hide buttons on the phone. ForgeDesk re-checks them on ingest.
                'permissions' => json_encode($canSignIn ? array_values(array_filter(
                    // `dev.report` is a pseudo-permission: administrators only (shows the bug-report button on the phone).
                    [...self::SHIPMENT_PERMISSIONS, 'dev.report'],
                    fn ($p) => $p === 'dev.report' ? $u->isAdmin() : $u->hasPermission($p)
                )) : []),
                'is_active' => (bool) $canSignIn,
                'synced_at' => $now,
            ];
        })->all();

        $remote->transaction(function () use ($remote, $rows) {
            foreach (array_chunk($rows, 200) as $chunk) {
                $remote->table('fa_users')->upsert($chunk, ['forgedesk_user_id'], ['login', 'display_name', 'password_hash', 'permissions', 'is_active', 'synced_at']);
            }
            // Revoked or deleted: keep the row (sessions/history reference it) but strip the credential.
            $remote->table('fa_users')->whereNotIn('forgedesk_user_id', array_column($rows, 'forgedesk_user_id'))
                ->update(['is_active' => false, 'password_hash' => '']);
        });

        return count($rows);
    }

    // ---- field app -> ForgeDesk ----------------------------------------------------------

    /** @return array{ingested: int, rejected: int, needs_review: int, skipped: int} */
    public function ingest(): array
    {
        $remote = $this->remote();
        $tally = ['ingested' => 0, 'rejected' => 0, 'needs_review' => 0, 'skipped' => 0];

        $rows = $remote->table('fa_submissions')->whereNull('ingested_at')
            ->orderBy('id')->limit((int) config('fleet.sync.batch_size'))->get();

        foreach ($rows as $row) {
            $uuid = (string) $row->submission_uuid;

            if (FieldSyncLog::where('submission_uuid', $uuid)->exists()) {
                $tally['skipped']++;   // already handled on an earlier (interrupted) run
                $this->markRemote($remote, $row->id);

                continue;
            }

            try {
                [$status, $error, $target] = $this->process($row, $remote);
            } catch (Throwable $e) {
                report($e);
                [$status, $error, $target] = ['needs_review', 'Unexpected error: '.Str::limit($e->getMessage(), 300), null];
            }

            FieldSyncLog::create([
                'submission_uuid' => $uuid,
                'kind' => Str::limit((string) $row->kind, 20, ''),
                'asset_number' => Str::limit((string) $row->asset_number, 100, ''),
                'status' => $status,
                'error' => $error,
                'payload' => json_decode((string) $row->payload, true) ?: ['raw' => Str::limit((string) $row->payload, 2000)],
                'target_type' => $target[0] ?? null,
                'target_id' => $target[1] ?? null,
                'ingested_at' => now(),
            ]);
            $tally[$status]++;
            $this->markRemote($remote, $row->id);
        }

        Cache::forever(self::CACHE_LAST_IN, now()->toIso8601String());

        return $tally;
    }

    private function markRemote(Connection $remote, $id): void
    {
        $remote->table('fa_submissions')->where('id', $id)->update(['ingested_at' => now()->toDateTimeString()]);
    }

    /** @return array{0: string, 1: ?string, 2: ?array} status, error, [target_type, target_id] */
    private function process(object $row, Connection $remote): array
    {
        $payload = json_decode((string) $row->payload, true);
        if (! is_array($payload)) {
            return ['rejected', 'Payload is not valid JSON.', null];
        }
        if (! in_array($row->kind, ['inspection', 'meter', 'defect', 'shipment_inspection', 'shipment_precheck', 'shipment_create', 'shipment_reschedule', 'shipment_ship', 'dev_report'], true)) {
            return ['rejected', 'Unknown submission kind.', null];
        }
        $by = Str::limit(trim((string) ($row->submitted_by ?? '')), 100, '') ?: 'field app';
        if (! Str::isUuid((string) $row->submission_uuid) && ! preg_match('/^[A-Za-z0-9_-]{8,64}$/', (string) $row->submission_uuid)) {
            return ['rejected', 'Invalid submission_uuid.', null];
        }

        if ($row->kind === 'dev_report') {
            return $this->ingestDevReport($row, $payload);
        }
        if ($row->kind === 'shipment_inspection') {
            return $this->ingestShipmentInspection($row, $payload, $by, $this->deviceTime($row->submitted_at ?? null), $remote);
        }
        if (in_array($row->kind, ['shipment_precheck', 'shipment_create', 'shipment_reschedule', 'shipment_ship'], true)) {
            // These change shipments, so the submitting person must hold the matching ForgeDesk permission.
            $user = $this->submitter($row, $row->kind === 'shipment_precheck' ? 'shipments.inspect' : 'shipments.manage');
            if (! $user) {
                return ['rejected', 'The submitting user does not have permission for this.', null];
            }

            return match ($row->kind) {
                'shipment_precheck' => $this->ingestShipmentInspection($row, $payload, $by, $this->deviceTime($row->submitted_at ?? null), $remote, 'pre'),
                'shipment_create' => DB::transaction(fn () => $this->ingestShipmentCreate($row, $payload, $user)),
                'shipment_reschedule' => $this->ingestShipmentReschedule($row, $payload),
                'shipment_ship' => $this->ingestShipmentShip($row),
            };
        }

        $asset = FieldAsset::with('type')->where('asset_number', (string) $row->asset_number)->first();
        if (! $asset) {
            return ['rejected', 'Unknown asset number.', null];
        }
        if (in_array($asset->status, FieldAsset::INACTIVE_STATUSES, true)) {
            return ['rejected', "Asset is {$asset->status}.", null];
        }

        $at = $this->deviceTime($row->submitted_at ?? null);

        return DB::transaction(fn () => match ($row->kind) {
            'meter' => $this->ingestMeter($asset, $payload, $by, $at),
            'defect' => $this->ingestDefect($asset, $row, $payload, $by, $remote),
            'inspection' => $this->ingestInspection($asset, $row, $payload, $by, $at, $remote),
        });
    }

    /** The shipment number travels in fa_submissions.asset_number for this kind. */
    private function ingestShipmentInspection(object $row, array $p, string $by, Carbon $at, Connection $remote, string $phase = 'post'): array
    {
        $shipment = Shipment::where('shipment_number', (string) $row->asset_number)->first();
        if (! $shipment) {
            return ['rejected', 'Unknown shipment number.', null];
        }
        if ($shipment->status === 'cancelled') {
            return ['rejected', 'Shipment was cancelled.', null];
        }
        if ($phase === 'pre' ? ! in_array($shipment->status, ['draft', 'pre_check_done'], true) : $shipment->status !== 'shipped') {
            return ['needs_review', "Shipment is {$shipment->status}, not awaiting a ".($phase === 'pre' ? 'pre-shipment' : 'delivery').' inspection.', null];
        }

        $v = Validator::make($p, [
            'notes' => 'nullable|string|max:5000', 'items' => 'required|array|min:1|max:200',
            'items.*.item_id' => 'required|integer', 'items.*.value' => 'nullable|string|max:255',
            'items.*.comment' => 'nullable|string|max:1000', 'items.*.photos' => 'nullable|array|max:30',
            'items.*.photos.*' => 'integer|min:0',
        ]);
        if ($v->fails()) {
            return ['rejected', $v->errors()->first(), null];
        }

        $answers = [];
        foreach ($p['items'] as $i) {
            $answers[(int) $i['item_id']] = ['value' => $i['value'] ?? null, 'comment' => $i['comment'] ?? null, 'photos' => $i['photos'] ?? []];
        }

        // Photos are matched to items by their position in upload order (ids ascend with insertion).
        $max = (int) config('fleet.sync.max_photo_bytes');
        $photos = [];
        foreach ($remote->table('fa_photos')->where('submission_uuid', (string) $row->submission_uuid)->orderBy('id')->limit(ShipmentInspectionService::MAX_PHOTOS)->get() as $photo) {
            $bytes = base64_decode((string) $photo->data_b64, true);
            $photos[] = ['bytes' => ($bytes !== false && strlen($bytes) <= $max) ? $bytes : '', 'name' => (string) $photo->filename];
        }

        try {
            $inspection = $this->shipments->record($shipment, $phase, $answers, $photos, [
                'external' => $by, 'source' => 'field_app', 'at' => $at, 'notes' => $p['notes'] ?? null, 'uuid' => (string) $row->submission_uuid, 'lenient_photos' => true,
            ]);
        } catch (ValidationException $e) {
            return ['rejected', collect($e->errors())->flatten()->first(), null];
        }

        if ($photos && config('fleet.sync.purge_ingested_photos')) {
            $remote->table('fa_photos')->where('submission_uuid', (string) $row->submission_uuid)->delete();
        }

        return ['ingested', null, ['shipment_inspection', $inspection->id]];
    }

    /** Bug / feature / note from an administrator on the phone, into the development tracker (contract v6). */
    private function ingestDevReport(object $row, array $p): array
    {
        $id = (int) ($row->submitted_by_user_id ?? 0);
        $user = $id ? User::find($id) : null;
        if (! $user || ! $user->is_active || $user->is_disabled || ! $user->mobile_app_access || ! $user->isAdmin()) {
            return ['rejected', 'Only administrators can send development reports.', null];
        }

        $v = Validator::make($p, [
            'type' => 'required|in:bug,feature,note',
            'title' => 'required|string|max:255',
            'priority' => 'nullable|in:future,low,normal,high,critical',
            'description' => 'nullable|string|max:5000',
            'console_output' => 'nullable|string|max:20000',
            'page' => 'nullable|string|max:200',
        ]);
        if ($v->fails()) {
            return ['rejected', 'Invalid dev report: '.$v->errors()->first(), null];
        }
        $d = $v->validated();

        $item = DevItem::create([
            'type' => $d['type'],
            'source' => 'field_app',
            'title' => $d['title'],
            'priority' => $d['priority'] ?? 'normal',
            'description' => $d['description'] ?? null,
            'console_output' => $d['console_output'] ?? null,
            'page_url' => $d['page'] ?? null,
            'user_agent' => 'Field app',
            'created_by' => $user->id,
        ]);

        return ['ingested', null, ['dev_item', $item->id]];
    }

    /** The submitting person, only if they are still allowed in and hold `$permission` in ForgeDesk. */
    private function submitter(object $row, string $permission): ?User
    {
        $id = (int) ($row->submitted_by_user_id ?? 0);
        $user = $id ? User::find($id) : null;

        return $user && $user->is_active && ! $user->is_disabled && $user->mobile_app_access && $user->hasPermission($permission) ? $user : null;
    }

    /** A shipment started on the phone. Job numbers that no longer match are noted on the shipment, not guessed. */
    private function ingestShipmentCreate(object $row, array $p, User $user): array
    {
        $v = Validator::make($p, [
            'ship_date' => 'nullable|date_format:Y-m-d', 'carrier' => 'nullable|string|max:255', 'destination' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000', 'job_numbers' => 'nullable|array|max:50', 'job_numbers.*' => 'string|max:60',
            'loads' => 'required|array|min:1|max:20', 'loads.*.type_id' => 'required|integer|distinct',
            'loads.*.piece_count' => 'nullable|integer|min:0|max:100000',
        ]);
        if ($v->fails()) {
            return ['rejected', $v->errors()->first(), null];
        }
        $typeIds = ShipmentType::where('is_active', true)->whereIn('id', array_column($p['loads'], 'type_id'))->pluck('id')->all();
        if (count($typeIds) !== count($p['loads'])) {
            return ['rejected', 'A load type is unknown or no longer active.', null];
        }
        $numbers = array_values(array_unique($p['job_numbers'] ?? []));
        $jobs = BusinessJob::whereIn('job_number', $numbers)->get();
        $missing = array_diff($numbers, $jobs->pluck('job_number')->all());
        $notes = trim((string) ($p['notes'] ?? ''));
        if ($missing) {
            $notes = trim($notes."\nJobs from the phone not found: ".implode(', ', $missing));
        }

        $shipment = Shipment::create([
            'shipment_number' => Shipment::nextNumber(), 'status' => 'draft', 'created_by' => $user->id,
            'ship_date' => $p['ship_date'] ?? null, 'carrier' => $p['carrier'] ?? null, 'destination' => $p['destination'] ?? null,
            'notes' => $notes !== '' ? Str::limit($notes, 5000, '') : null,
        ]);
        $shipment->jobs()->sync($jobs->pluck('id')->all());
        foreach ($p['loads'] as $l) {
            $shipment->loads()->create(['shipment_type_id' => (int) $l['type_id'], 'piece_count' => $l['piece_count'] ?? null]);
        }

        return ['ingested', null, ['shipment', $shipment->id]];
    }

    /** Same rule as the web "Mark shipped": only after a passing pre-check. This is what publishes it for the delivery check. */
    private function ingestShipmentShip(object $row): array
    {
        $shipment = Shipment::where('shipment_number', (string) $row->asset_number)->first();
        if (! $shipment) {
            return ['rejected', 'Unknown shipment number.', null];
        }
        if ($shipment->status !== 'pre_check_done') {
            return ['needs_review', "Shipment is {$shipment->status}, not ready to ship.", null];
        }
        $shipment->update(['status' => 'shipped', 'shipped_at' => now(), 'ship_date' => $shipment->ship_date ?? now()->toDateString()]);

        return ['ingested', null, ['shipment', $shipment->id]];
    }

    private function ingestShipmentReschedule(object $row, array $p): array
    {
        $v = Validator::make($p, ['ship_date' => 'present|nullable|date_format:Y-m-d']);
        if ($v->fails()) {
            return ['rejected', $v->errors()->first(), null];
        }
        $shipment = Shipment::where('shipment_number', (string) $row->asset_number)->first();
        if (! $shipment) {
            return ['rejected', 'Unknown shipment number.', null];
        }
        if (! in_array($shipment->status, ['draft', 'pre_check_done'], true)) {
            return ['needs_review', "Shipment is {$shipment->status} and cannot be rescheduled.", null];
        }
        $shipment->update(['ship_date' => $p['ship_date']]);

        return ['ingested', null, ['shipment', $shipment->id]];
    }

    private function ingestMeter(FieldAsset $asset, array $p, string $by, Carbon $at): array
    {
        $v = Validator::make($p, ['reading' => 'required|numeric|min:0|max:99999999']);
        if ($v->fails()) {
            return ['rejected', $v->errors()->first(), null];
        }
        $reading = (float) $p['reading'];
        if ($asset->current_meter !== null && $reading < (float) $asset->current_meter) {
            return ['needs_review', "Reading {$reading} is below the current meter ({$asset->current_meter}).", null];
        }
        $row = $this->fleet->recordMeter($asset, $reading, 'field_app', null, false, $at, $by);

        return ['ingested', null, ['meter_reading', $row->id]];
    }

    private function ingestDefect(FieldAsset $asset, object $row, array $p, string $by, Connection $remote): array
    {
        $v = Validator::make($p, [
            'title' => 'required|string|max:255', 'description' => 'nullable|string|max:5000',
            'severity' => 'nullable|in:low,medium,high,critical',
        ]);
        if ($v->fails()) {
            return ['rejected', $v->errors()->first(), null];
        }

        $wo = FieldAssetWorkOrder::create([
            'wo_number' => FieldAssetWorkOrder::nextNumber(),
            'field_asset_id' => $asset->id, 'type' => 'repair', 'status' => 'open',
            'priority' => $p['severity'] ?? 'medium', 'title' => $p['title'],
            'description' => $p['description'] ?? null, 'reported_on' => now()->toDateString(),
            'reported_by_external' => $by, 'source' => 'field_app',
        ]);
        $this->attachPhotos($remote, (string) $row->submission_uuid, $asset, ['work_order_id' => $wo->id]);
        $this->fleet->notify('fleet_defect', $wo->priority === 'critical' ? 'danger' : 'warning',
            "Defect reported: {$asset->asset_number} ({$wo->wo_number})", "{$by} reported \"{$wo->title}\" on {$asset->name} ({$wo->wo_number}).",
            ['asset_id' => $asset->id, 'work_order_id' => $wo->id]);

        return ['ingested', null, ['work_order', $wo->id]];
    }

    private function ingestInspection(FieldAsset $asset, object $row, array $p, string $by, Carbon $at, Connection $remote): array
    {
        $v = Validator::make($p, [
            'template_name' => 'nullable|string|max:255', 'meter' => 'nullable|numeric|min:0|max:99999999',
            'notes' => 'nullable|string|max:5000', 'items' => 'required|array|min:1|max:100',
            'items.*.label' => 'required|string|max:255', 'items.*.result' => 'required|in:pass,fail,na',
            'items.*.comment' => 'nullable|string|max:1000',
        ]);
        if ($v->fails()) {
            return ['rejected', $v->errors()->first(), null];
        }

        // "Critical" is decided by ForgeDesk's checklist, never by the (untrusted) client.
        $critical = collect($asset->type->default_checklist ?? [])->filter(fn ($i) => ! empty($i['critical']))
            ->pluck('label')->map(fn ($l) => mb_strtolower($l))->all();
        $items = array_map(fn ($i) => [
            'label' => $i['label'], 'result' => $i['result'], 'comment' => $i['comment'] ?? null,
            'critical' => in_array(mb_strtolower($i['label']), $critical, true),
        ], $p['items']);
        $result = FieldAssetInspection::resultFor($items);

        $inspection = FieldAssetInspection::create([
            'field_asset_id' => $asset->id, 'template_name' => $p['template_name'] ?? null,
            'submitted_by_external' => $by, 'performed_at' => $at, 'meter' => $p['meter'] ?? null,
            'result' => $result, 'notes' => $p['notes'] ?? null, 'source' => 'field_app',
            'submission_uuid' => (string) $row->submission_uuid,
        ]);
        $inspection->items()->createMany($items);

        if (isset($p['meter']) && ($asset->current_meter === null || (float) $p['meter'] >= (float) $asset->current_meter)) {
            $this->fleet->recordMeter($asset, (float) $p['meter'], 'field_app', null, false, $at, $by);
        }
        $this->attachPhotos($remote, (string) $row->submission_uuid, $asset, ['inspection_id' => $inspection->id]);

        if ($result !== 'pass') {
            $wo = $this->fleet->defectWorkOrder($inspection, 'field_app');
            $this->fleet->notify('fleet_inspection_fail', $result === 'fail' ? 'danger' : 'warning',
                "Inspection {$result}: {$asset->asset_number} ({$wo->wo_number})", "{$by} inspected {$asset->name}: ".str_replace('_', ' ', $result).". Work order {$wo->wo_number} opened.",
                ['asset_id' => $asset->id, 'inspection_id' => $inspection->id, 'work_order_id' => $wo->id]);
            if ($result === 'fail' && $asset->type->auto_out_of_service_on_fail) {
                $asset->update(['status' => 'out_of_service']);
            }
        }

        return ['ingested', null, ['inspection', $inspection->id]];
    }

    /** Clamp absurd device clocks to "now" so a bad phone clock can't backdate history. */
    private function deviceTime($value): Carbon
    {
        try {
            $t = $value ? Carbon::parse($value) : now();
        } catch (Throwable) {
            return now();
        }
        $skew = (int) config('fleet.sync.max_clock_skew_days');

        return ($t->gt(now()->addDay()) || $t->lt(now()->subDays($skew))) ? now() : $t;
    }

    /** Pull photo blobs for a submission, verify they really are images, store them privately. */
    private function attachPhotos(Connection $remote, string $uuid, FieldAsset $asset, array $link): void
    {
        $photos = $remote->table('fa_photos')->where('submission_uuid', $uuid)->limit(10)->get();
        $max = (int) config('fleet.sync.max_photo_bytes');

        foreach ($photos as $photo) {
            $bytes = base64_decode((string) $photo->data_b64, true);
            if ($bytes === false || $bytes === '' || strlen($bytes) > $max) {
                continue;
            }
            $mime = ImageSanitizer::sniff($bytes);
            if (! $mime) {
                continue; // spoofed filename / not an image
            }
            $bytes = ImageSanitizer::reencode($bytes, $mime);
            $path = "field_assets/{$asset->id}/".Str::uuid().'.'.self::PHOTO_MIMES[$mime];
            Storage::disk('local')->put($path, $bytes);

            FieldAssetDocument::create($link + [
                'field_asset_id' => $asset->id, 'doc_type' => 'photo', 'label' => 'Field photo',
                'original_name' => Str::limit(basename((string) $photo->filename), 100, '') ?: 'photo.'.self::PHOTO_MIMES[$mime],
                'file_path' => $path, 'file_size' => strlen($bytes), 'file_mime' => $mime,
            ]);
        }

        if ($photos->isNotEmpty() && config('fleet.sync.purge_ingested_photos')) {
            $remote->table('fa_photos')->where('submission_uuid', $uuid)->delete();
        }
    }
}
