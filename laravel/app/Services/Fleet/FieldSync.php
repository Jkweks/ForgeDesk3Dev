<?php

namespace App\Services\Fleet;

use App\Models\FieldAsset;
use App\Models\FieldAssetDocument;
use App\Models\FieldAssetInspection;
use App\Models\FieldAssetType;
use App\Models\FieldAssetWorkOrder;
use App\Models\FieldSyncLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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

    private const PHOTO_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(private FleetService $fleet) {}

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
     * @return array{assets: int, checklists: int, users: int}
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

        Cache::forever(self::CACHE_LAST_OUT, now()->toIso8601String());

        return ['assets' => count($rows), 'checklists' => count($checklists), 'users' => $users];
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
                'is_active' => (bool) $canSignIn,
                'synced_at' => $now,
            ];
        })->all();

        $remote->transaction(function () use ($remote, $rows) {
            foreach (array_chunk($rows, 200) as $chunk) {
                $remote->table('fa_users')->upsert($chunk, ['forgedesk_user_id'], ['login', 'display_name', 'password_hash', 'is_active', 'synced_at']);
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
        if (! in_array($row->kind, ['inspection', 'meter', 'defect'], true)) {
            return ['rejected', 'Unknown submission kind.', null];
        }
        $by = Str::limit(trim((string) ($row->submitted_by ?? '')), 100, '') ?: 'field app';
        if (! Str::isUuid((string) $row->submission_uuid) && ! preg_match('/^[A-Za-z0-9_-]{8,64}$/', (string) $row->submission_uuid)) {
            return ['rejected', 'Invalid submission_uuid.', null];
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
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            if (! isset(self::PHOTO_MIMES[$mime])) {
                continue; // spoofed filename / not an image
            }
            $bytes = $this->reencode($bytes, $mime);
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

    /** Re-encode JPEG/PNG through GD: strips EXIF (incl. GPS) and any trailing payload. */
    private function reencode(string $bytes, string $mime): string
    {
        if (! function_exists('imagecreatefromstring') || ! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return $bytes;
        }
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            return $bytes;
        }
        ob_start();
        $mime === 'image/png' ? imagepng($img) : imagejpeg($img, null, 88);
        $out = ob_get_clean();
        imagedestroy($img);

        return $out ?: $bytes;
    }
}
