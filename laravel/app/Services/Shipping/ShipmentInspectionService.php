<?php

namespace App\Services\Shipping;

use App\Models\Shipment;
use App\Models\ShipmentCheckItem;
use App\Models\ShipmentInspection;
use App\Models\SystemNotification;
use App\Services\ImageSanitizer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Records pre-shipment (office, web) and post-delivery (field app) inspections.
 *
 * Both paths go through here so the rules are identical: answers are checked against the
 * checklist ForgeDesk holds (never what the client claims), "critical" comes from ForgeDesk,
 * and photos are verified as images and re-encoded before being stored privately.
 */
class ShipmentInspectionService
{
    public const MAX_PHOTOS = 30;

    /** Active checklist items for the shipment's load types, in display order. */
    public function items(Shipment $shipment, string $phase): Collection
    {
        $typeIds = $shipment->loads()->pluck('shipment_type_id');

        return ShipmentCheckItem::with('type:id,name,sort_order')
            ->whereIn('shipment_type_id', $typeIds)
            ->where('phase', $phase)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->sortBy(fn ($i) => [$i->type->sort_order, $i->type->id, $i->sort_order, $i->id])->values();
    }

    /** Checklist grouped per type, as sent to forms and to the field app. */
    public function checklist(Shipment $shipment, string $phase): array
    {
        return $this->items($shipment, $phase)->groupBy('shipment_type_id')->map(fn ($items) => [
            'type_id' => $items->first()->type->id,
            'type_name' => $items->first()->type->name,
            'items' => $items->map(fn ($i) => [
                'id' => $i->id, 'label' => $i->label, 'input_type' => $i->input_type, 'unit' => $i->unit,
                'options' => $i->options, 'required' => $i->required, 'photo_required' => $i->photo_required,
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * @param  array<int, array{value?: mixed, comment?: ?string, photos?: int[]}>  $answers  keyed by check item id; `photos` are indexes into $photos
     * `lenient_photos`: don't reject for a missing required photo (the field app enforces it on the phone;
     * a lost photo must never cost us the crew's damage report).
     *
     * @param  array<int, array{bytes: string, name?: string}>  $photos
     * @param  array{user_id?: ?int, external?: ?string, source?: string, at?: ?Carbon, notes?: ?string, uuid?: ?string, lenient_photos?: bool}  $meta
     *
     * @throws ValidationException
     */
    public function record(Shipment $shipment, string $phase, array $answers, array $photos, array $meta = []): ShipmentInspection
    {
        $defs = $this->items($shipment, $phase)->keyBy('id');
        if ($defs->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'This shipment has no '.$phase.'-inspection checklist. Add a load type first.']);
        }
        if (count($photos) > self::MAX_PHOTOS) {
            throw ValidationException::withMessages(['photos' => 'At most '.self::MAX_PHOTOS.' photos per inspection.']);
        }

        $errors = [];
        $rows = [];
        foreach (array_keys($answers) as $id) {
            if (! $defs->has((int) $id)) {
                $errors['items'] = 'Unknown checklist item.';
            }
        }
        foreach ($defs as $id => $def) {
            $a = $answers[$id] ?? null;
            $mine = array_values(array_filter(array_map('intval', (array) ($a['photos'] ?? [])), fn ($i) => isset($photos[$i])));
            if ($def->input_type === 'photo') {
                // The answer IS the photos: answered when at least one is attached. The field app enforces
                // this on the phone, so a photo that got lost on the way must not cost us the rest of the report.
                $claimed = $a !== null && is_numeric($a['value'] ?? null) && (int) $a['value'] > 0;
                $given = $mine || (! empty($meta['lenient_photos']) && $claimed);
                if ($given) {
                    $rows[$id] = [
                        'shipment_type_id' => $def->shipment_type_id, 'type_name' => $def->type->name, 'label' => $def->label,
                        'input_type' => 'photo', 'unit' => null, 'value' => (string) count($mine), 'result' => 'pass', 'critical' => false,
                        'comment' => Str::limit(trim((string) ($a['comment'] ?? '')), 1000, '') ?: null, '_photos' => $mine,
                    ];
                } elseif ($def->required) {
                    $errors["items.$id"] = "\"{$def->label}\" needs at least one photo.";
                }

                continue;
            }
            $given = $a !== null && isset($a['value']) && trim((string) $a['value']) !== '';
            if (! $given) {
                if ($def->required) {
                    $errors["items.$id"] = "\"{$def->label}\" is required.";
                }

                continue;
            }
            [$value, $result, $err] = $this->normalise($def, trim((string) $a['value']));
            if ($err) {
                $errors["items.$id"] = $err;

                continue;
            }
            if (empty($meta['lenient_photos']) && $def->photo_required && ($def->input_type !== 'pass_fail' || $result === 'fail') && ! $mine) {
                $errors["items.$id"] = "\"{$def->label}\" needs a photo.";
            }
            $rows[$id] = [
                'shipment_type_id' => $def->shipment_type_id, 'type_name' => $def->type->name, 'label' => $def->label,
                'input_type' => $def->input_type, 'unit' => $def->unit, 'value' => Str::limit($value, 255, ''),
                'result' => $result, 'critical' => (bool) $def->critical,
                'comment' => Str::limit(trim((string) ($a['comment'] ?? '')), 1000, '') ?: null, '_photos' => $mine,
            ];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        if (! $rows) {
            throw ValidationException::withMessages(['items' => 'Nothing was answered.']);
        }

        return DB::transaction(function () use ($shipment, $phase, $rows, $photos, $meta) {
            $result = ShipmentInspection::resultFor($rows);
            $inspection = ShipmentInspection::create([
                'shipment_id' => $shipment->id, 'phase' => $phase, 'result' => $result,
                'performed_by_user_id' => $meta['user_id'] ?? null, 'submitted_by_external' => $meta['external'] ?? null,
                'performed_at' => $meta['at'] ?? now(), 'notes' => isset($meta['notes']) ? Str::limit(trim((string) $meta['notes']), 5000, '') ?: null : null,
                'source' => $meta['source'] ?? 'office', 'submission_uuid' => $meta['uuid'] ?? null,
            ]);

            $stored = [];   // photo index => true once attached to an item
            foreach ($rows as $row) {
                $idx = $row['_photos'];
                unset($row['_photos']);
                $item = $inspection->items()->create($row);
                foreach ($idx as $i) {
                    $this->storePhoto($inspection, $item->id, $photos[$i]);
                    $stored[$i] = true;
                }
            }
            foreach ($photos as $i => $photo) {   // photos not tied to an item are kept on the inspection
                if (empty($stored[$i])) {
                    $this->storePhoto($inspection, null, $photo);
                }
            }

            $this->applyOutcome($shipment, $inspection);

            return $inspection;
        });
    }

    /** @return array{0: string, 1: string, 2: ?string} value, result (pass|fail|na), error */
    private function normalise(ShipmentCheckItem $def, string $raw): array
    {
        return match ($def->input_type) {
            'pass_fail' => in_array($raw, ['pass', 'fail', 'na'], true)
                ? [$raw, $raw, null] : ['', 'pass', "\"{$def->label}\" must be pass, fail or n/a."],
            'number' => is_numeric($raw) && abs((float) $raw) < 1e9
                ? [$raw, 'pass', null] : ['', 'pass', "\"{$def->label}\" must be a number."],
            'choice' => in_array($raw, (array) $def->options, true)
                ? [$raw, 'pass', null] : ['', 'pass', "\"{$def->label}\" has an invalid choice."],
            default => [Str::limit($raw, 255, ''), 'pass', null],
        };
    }

    private function storePhoto(ShipmentInspection $inspection, ?int $itemId, array $photo): void
    {
        $bytes = $photo['bytes'] ?? '';
        $mime = ImageSanitizer::sniff($bytes);
        if (! $mime) {
            return; // not a real image: dropped, never stored
        }
        $bytes = ImageSanitizer::reencode($bytes, $mime);
        $path = "shipments/{$inspection->shipment_id}/".Str::uuid().'.'.ImageSanitizer::MIMES[$mime];
        Storage::disk('local')->put($path, $bytes);
        $inspection->photos()->create([
            'item_id' => $itemId, 'file_path' => $path, 'file_size' => strlen($bytes), 'file_mime' => $mime,
            'original_name' => Str::limit(basename((string) ($photo['name'] ?? '')), 100, '') ?: 'photo.'.ImageSanitizer::MIMES[$mime],
        ]);
    }

    /** Move the shipment along and raise an alert when something is wrong. */
    private function applyOutcome(Shipment $shipment, ShipmentInspection $inspection): void
    {
        $ok = $inspection->result === 'pass';
        $by = $inspection->performer?->name ?? $inspection->submitted_by_external ?? 'someone';

        if ($inspection->phase === 'pre') {
            // A completed (non-failed) pre-check ships the load automatically; a failed one leaves it in draft.
            if ($inspection->result !== 'fail' && in_array($shipment->status, ['draft', 'pre_check_done'], true)) {
                $shipment->update(['status' => 'shipped', 'shipped_at' => now(), 'ship_date' => $shipment->ship_date ?? now()->toDateString()]);
            }
            if ($inspection->result === 'fail') {
                $this->notify('shipment_pre_fail', 'danger', "Pre-shipment check failed: {$shipment->shipment_number}",
                    "{$by} failed a critical item on {$shipment->shipment_number} before it shipped.", $shipment, $inspection);
            }

            return;
        }

        // A received delivery does not close the shipment: the office verifies it (see verify()).
        $shipment->update(['status' => 'awaiting_verification']);
        $label = $shipment->jobLabel() ? ' ('.$shipment->jobLabel().')' : '';
        if (! $ok) {
            $this->notify('shipment_post_issues', $inspection->result === 'fail' ? 'danger' : 'warning',
                "Delivery issues: {$shipment->shipment_number}",
                "{$by} reported problems on delivery of {$shipment->shipment_number}{$label}. Review it and verify the delivery.", $shipment, $inspection);
        } else {
            $this->notify('shipment_awaiting_verification', 'info',
                "Delivery received: {$shipment->shipment_number}",
                "{$by} received {$shipment->shipment_number}{$label}. Please verify it and upload the signed shipping ticket.", $shipment, $inspection);
        }
    }

    /**
     * Office sign-off on a received delivery. The final status follows the delivery inspection:
     * clean = delivered, any failed item = delivered_issues.
     */
    public function verify(Shipment $shipment, ?int $userId, ?string $notes = null): Shipment
    {
        $post = $shipment->inspections()->where('phase', 'post')->first();   // newest first
        $final = ($post && $post->result !== 'pass') ? 'delivered_issues' : 'delivered';

        $shipment->update([
            'status' => $final, 'verified_at' => now(), 'verified_by' => $userId,
            'verification_notes' => $notes !== null ? (Str::limit(trim($notes), 2000, '') ?: null) : null,
        ]);
        // The "please verify" prompts are done with.
        SystemNotification::active()->whereIn('type', ['shipment_awaiting_verification', 'shipment_post_issues'])->get()
            ->filter(fn ($n) => ($n->data['shipment_id'] ?? null) === $shipment->id)
            ->each(fn ($n) => $n->update(['dismissed_at' => now(), 'dismissed_by_user_id' => $userId]));

        return $shipment;
    }

    private function notify(string $type, string $level, string $title, string $message, Shipment $shipment, ShipmentInspection $inspection): void
    {
        SystemNotification::create(['type' => $type, 'level' => $level, 'title' => $title, 'message' => $message,
            'data' => ['shipment_id' => $shipment->id, 'inspection_id' => $inspection->id]]);
    }
}
