<?php

namespace App\Services\Configurator;

use App\Models\DoorFrameConfiguration;
use App\Models\JobReservation;
use App\Models\JobReservationItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Commits a released configuration's generated BOM (frame + door + hardware
 * parts) against real inventory by creating a JobReservation, the same
 * mechanism every other ForgeDesk fulfillment path uses — so a configured
 * opening actually reduces quantity_available like any other reservation,
 * not just a number on a report.
 */
class ConfigurationReservationBridge
{
    /**
     * Aggregates every generated (or manual) BOM line across the frame,
     * door, and hardware sections by product_id, and creates one
     * JobReservation with one item per product. Idempotent: if the
     * configuration already has a reservation, that one is returned as-is
     * rather than creating a duplicate.
     */
    public function createReservation(DoorFrameConfiguration $config, ?User $requestedBy = null): JobReservation
    {
        if ($config->job_reservation_id) {
            return $config->jobReservation;
        }

        $config->loadMissing([
            'businessJob',
            'frameConfig.parts',
            'doorConfigs.parts',
            'hardwareParts',
        ]);

        $lines = collect()
            ->merge($config->frameConfig?->parts ?? [])
            ->merge($config->doorConfigs->flatMap(fn ($dc) => $dc->parts))
            ->merge($config->hardwareParts);

        if ($lines->isEmpty()) {
            throw new RuntimeException('No BOM lines have been generated yet — generate the frame/door/hardware parts before reserving.');
        }

        // Merge duplicate products (e.g. the same fastener PN used by both
        // the frame and hardware sections) by summing quantity.
        $merged = $lines->groupBy('product_id')->map(fn ($group) => $group->sum('quantity'));

        return DB::transaction(function () use ($config, $merged, $requestedBy) {
            $reservation = JobReservation::create([
                'business_job_id' => $config->business_job_id,
                'job_number' => $config->businessJob->job_number,
                'job_name' => $config->businessJob->job_name,
                'requested_by' => $requestedBy?->name ?? 'Configurator Release',
                'requested_by_id' => $requestedBy?->id,
                'status' => 'active',
                'notes' => "Auto-created from door/frame configuration #{$config->id} on release.",
            ]);

            foreach ($merged as $productId => $qty) {
                JobReservationItem::create([
                    'reservation_id' => $reservation->id,
                    'product_id' => $productId,
                    'requested_qty' => $qty,
                    'committed_qty' => $qty,
                    'consumed_qty' => 0,
                ]);
            }

            $config->update(['job_reservation_id' => $reservation->id]);

            return $reservation;
        });
    }
}
