<?php

namespace App\Services\Fleet;

use App\Models\FieldAsset;
use App\Models\FieldAssetCompliance;
use App\Models\FieldAssetDocument;
use App\Models\FieldAssetInspection;
use App\Models\FieldAssetMaintPlan;
use App\Models\FieldAssetMeterReading;
use App\Models\FieldAssetWorkOrder;
use App\Models\SystemNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FleetService
{
    /**
     * Append a meter reading and move the asset's current meter forward.
     * A reading below the current meter is refused unless flagged as a correction.
     */
    public function recordMeter(FieldAsset $asset, float $reading, string $source = 'manual', ?int $userId = null, bool $correction = false, ?Carbon $readAt = null, ?string $external = null): FieldAssetMeterReading
    {
        if ($asset->current_meter !== null && $reading < (float) $asset->current_meter && ! $correction) {
            throw new InvalidArgumentException(sprintf(
                'Reading %s is below the current meter (%s). Mark it as a correction if this is intentional.',
                $reading, $asset->current_meter
            ));
        }

        return DB::transaction(function () use ($asset, $reading, $source, $userId, $correction, $readAt, $external) {
            $row = FieldAssetMeterReading::create([
                'field_asset_id' => $asset->id,
                'reading' => $reading,
                'read_at' => $readAt ?? now(),
                'recorded_by' => $userId,
                'recorded_by_external' => $external,
                'source' => $source,
                'is_correction' => $correction,
            ]);
            $asset->forceFill(['current_meter' => $reading, 'meter_updated_at' => $row->read_at])->save();

            return $row;
        });
    }

    /** Recompute next-due date/meter from the last completion (or the baseline if never done). */
    public function recalcPlan(FieldAssetMaintPlan $plan): FieldAssetMaintPlan
    {
        $baseDate = $plan->last_done_at ?? $plan->starts_on;
        $baseMeter = $plan->last_done_meter ?? $plan->starts_meter;

        $plan->next_due_at = ($plan->interval_days && $baseDate) ? $baseDate->copy()->addDays($plan->interval_days) : null;
        $plan->next_due_meter = ($plan->interval_meter !== null && $baseMeter !== null)
            ? (float) $baseMeter + (float) $plan->interval_meter : null;
        $plan->save();

        return $plan;
    }

    /** Mark a work order done; advances its maintenance plan and logs the meter. */
    public function completeWorkOrder(FieldAssetWorkOrder $wo, array $data, ?int $userId = null): FieldAssetWorkOrder
    {
        return DB::transaction(function () use ($wo, $data, $userId) {
            $wo->fill($data);
            $wo->status = 'completed';
            $wo->completed_on = $wo->completed_on ?? now()->toDateString();
            $wo->save();

            $asset = $wo->asset;

            // A service-time meter below the live meter is a historical entry on the work
            // order only; it never rolls the asset's current meter backwards.
            if ($wo->meter_at_service !== null
                && ($asset->current_meter === null || (float) $wo->meter_at_service >= (float) $asset->current_meter)) {
                $this->recordMeter($asset, (float) $wo->meter_at_service, 'work_order', $userId);
            }

            if ($wo->plan_id && $wo->plan) {
                $plan = $wo->plan;
                $plan->last_done_at = $wo->completed_on;
                $plan->last_done_meter = $wo->meter_at_service ?? $asset->current_meter;
                $this->recalcPlan($plan);
            }

            if ($asset->status === 'in_repair' && ! $asset->workOrders()->open()->where('id', '!=', $wo->id)->exists()) {
                $asset->update(['status' => 'active']);
            }

            return $wo->fresh(['plan', 'items']);
        });
    }

    /** Open an inspection_defect work order for failed items of an inspection. */
    public function defectWorkOrder(FieldAssetInspection $inspection, ?string $source = null): FieldAssetWorkOrder
    {
        $failed = $inspection->items()->where('result', 'fail')->get();
        $critical = $failed->contains(fn ($i) => $i->critical);

        return FieldAssetWorkOrder::create([
            'wo_number' => FieldAssetWorkOrder::nextNumber(),
            'field_asset_id' => $inspection->field_asset_id,
            'type' => 'inspection_defect',
            'status' => 'open',
            'priority' => $critical ? 'critical' : 'high',
            'title' => 'Inspection defects: '.($failed->pluck('label')->take(3)->implode(', ') ?: 'see inspection'),
            'description' => $failed->map(fn ($i) => '• '.$i->label.($i->comment ? ' — '.$i->comment : ''))->implode("\n"),
            'reported_on' => now()->toDateString(),
            'reported_by_user_id' => $inspection->performed_by_user_id,
            'reported_by_external' => $inspection->submitted_by_external,
            'source' => $source ?? $inspection->source,
            'inspection_id' => $inspection->id,
        ]);
    }

    /**
     * Post an in-app alert, once per (type, title). Dismissed alerts are not re-raised,
     * so titles must carry whatever makes a repeat genuinely new (WO number, due date, threshold).
     */
    public function notify(string $type, string $level, string $title, string $message, array $data = []): void
    {
        if (SystemNotification::ofType($type)->where('title', $title)->exists()) {
            return;
        }
        SystemNotification::create(compact('type', 'level', 'title', 'message', 'data'));
    }

    /**
     * Everything that needs attention: maintenance plans overdue/due soon, compliance
     * records and documents expiring within $days (or already expired).
     *
     * @return array{plans: array, compliance: array, documents: array}
     */
    public function upcoming(int $days = 90): array
    {
        $plans = FieldAssetMaintPlan::with('asset:id,asset_number,name,current_meter,status')
            ->where('is_active', true)
            ->whereHas('asset', fn ($a) => $a->inService())
            ->get()
            ->filter(fn ($p) => $p->due_state !== 'ok')
            ->sortBy(fn ($p) => [$p->due_state === 'overdue' ? 0 : 1, $p->next_due_at?->timestamp ?? PHP_INT_MAX])
            ->values()
            ->map(fn ($p) => [
                'id' => $p->id, 'title' => $p->title, 'state' => $p->due_state, 'priority' => $p->priority,
                'next_due_at' => $p->next_due_at?->format('Y-m-d'), 'next_due_meter' => $p->next_due_meter,
                'asset' => ['id' => $p->asset->id, 'asset_number' => $p->asset->asset_number, 'name' => $p->asset->name, 'current_meter' => $p->asset->current_meter],
            ])->all();

        $cutoff = now()->addDays($days)->toDateString();
        $compliance = FieldAssetCompliance::with('asset:id,asset_number,name')
            ->whereNotNull('expires_on')->whereDate('expires_on', '<=', $cutoff)
            ->whereHas('asset', fn ($a) => $a->inService())
            // Superseded records (a newer one of the same kind exists) are not alerts.
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('field_asset_compliance as newer')
                ->whereColumn('newer.field_asset_id', 'field_asset_compliance.field_asset_id')
                ->whereColumn('newer.kind', 'field_asset_compliance.kind')
                ->whereColumn('newer.expires_on', '>', 'field_asset_compliance.expires_on')
                ->whereNull('newer.deleted_at'))
            ->orderBy('expires_on')->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'kind' => $c->kind, 'expires_on' => $c->expires_on->format('Y-m-d'),
                'days' => $c->days_until_expiry, 'state' => $c->days_until_expiry < 0 ? 'expired' : 'expiring',
                'asset' => ['id' => $c->asset->id, 'asset_number' => $c->asset->asset_number, 'name' => $c->asset->name],
            ])->all();

        $documents = FieldAssetDocument::with('asset:id,asset_number,name')
            ->whereNotNull('expires_on')->whereDate('expires_on', '<=', $cutoff)
            ->whereHas('asset', fn ($a) => $a->inService())
            ->orderBy('expires_on')->get()
            ->map(fn ($d) => [
                'id' => $d->id, 'label' => $d->label ?: $d->original_name, 'doc_type' => $d->doc_type,
                'expires_on' => $d->expires_on->format('Y-m-d'),
                'state' => $d->expires_on->lt(now()->startOfDay()) ? 'expired' : 'expiring',
                'asset' => ['id' => $d->asset->id, 'asset_number' => $d->asset->asset_number, 'name' => $d->asset->name],
            ])->all();

        return compact('plans', 'compliance', 'documents');
    }
}
