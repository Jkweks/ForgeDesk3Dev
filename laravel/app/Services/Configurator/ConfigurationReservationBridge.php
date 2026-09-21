<?php

namespace App\Services\Configurator;

use App\Models\DoorFrameConfiguration;
use App\Models\JobReservation;
use App\Models\JobReservationItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Commits a configuration's generated BOM (frame + door + hardware parts)
 * against real inventory by creating (or, on later calls, syncing) a
 * JobReservation — the same mechanism every other ForgeDesk fulfillment path
 * uses, so a configured opening actually reduces quantity_available like any
 * other reservation, not just a number on a report.
 *
 * Used both when a configuration is first reserved (still editable) and
 * again at release — and, while a configuration stays in the editable
 * "reserved" status, every time its generated parts change, to keep the
 * reservation's item quantities in sync rather than drifting from the BOM.
 */
class ConfigurationReservationBridge
{
    /**
     * Never blocks — a configuration with nothing generated yet (or only
     * some sections generated) can still be reserved; incomplete sections
     * just come back as warnings instead of committing anything for them.
     * If literally nothing has been generated anywhere and there's no
     * existing reservation to sync, this is a no-op (reservation: null).
     *
     * @return array{reservation: ?JobReservation, warnings: string[]}
     */
    public function reserve(DoorFrameConfiguration $config, ?User $requestedBy = null): array
    {
        $config->loadMissing([
            'businessJob',
            'frameConfig.parts.product',
            'doorConfigs.parts.product',
            'hardwareParts',
        ]);

        $warnings = [];
        if ($config->includesFrame() && ! $config->frameConfig?->parts->count()) {
            $warnings[] = 'Frame parts have not been generated yet.';
        }
        if ($config->includesDoor() && $config->doorConfigs->flatMap(fn ($dc) => $dc->parts)->isEmpty()) {
            $warnings[] = 'Door parts have not been generated yet.';
        }
        if ($config->hardwareParts->isEmpty()) {
            $warnings[] = 'Hardware parts have not been generated yet.';
        }

        $lines = collect()
            ->merge($config->frameConfig?->parts ?? [])
            ->merge($config->doorConfigs->flatMap(fn ($dc) => $dc->parts))
            ->merge($config->hardwareParts);

        // Merge duplicate products (e.g. the same fastener PN used by both
        // the frame and hardware sections) by summing each line's quantity
        // contribution — for a length-based stock product this is a
        // fraction-of-a-stick (rounded up to the next 1/10th), not "1 per cut".
        $desired = $lines->groupBy('product_id')->map(
            fn ($group) => $group->sum(fn ($part) => $this->quantityContribution($part))
        );

        if ($desired->isEmpty() && ! $config->job_reservation_id) {
            $warnings[] = 'Nothing has been generated yet — no inventory has been committed. Reservation will be created automatically once parts are generated.';

            return ['reservation' => null, 'warnings' => $warnings];
        }

        $reservation = $config->job_reservation_id
            ? $this->syncReservation($config, $desired, $warnings)
            : $this->createReservation($config, $desired, $requestedBy);

        return ['reservation' => $reservation, 'warnings' => $warnings];
    }

    /**
     * How much of a BOM line's product should count toward the reservation.
     * For an ordinary (unit-counted) part, that's just its quantity column.
     * For a length-based stock product (e.g. a 288" extrusion, cut to a
     * calculated length), it's the cut length expressed as a fraction of one
     * stick — rounded UP to the next 1/10th, so a 145" cut against a 288"
     * stick reserves 0.6 sticks, not the exact 0.503472... — matching
     * JobReservationItem::binAwareCommitted()'s own 1/10th-stick bin-packing
     * downstream, which assumes committed_qty is already in these units.
     */
    private function quantityContribution($part): float
    {
        $product = $part->product ?? null;

        if (
            $product?->is_length_based
            && (float) ($product->configurator_length ?? 0) > 0
            && ($part->unit_type ?? null) === 'length'
            && $part->calculated_length
        ) {
            $sticksPerCut = $part->calculated_length / $product->configurator_length;
            $roundedUp = ceil($sticksPerCut * 10) / 10;

            return $roundedUp * (float) $part->quantity;
        }

        return (float) $part->quantity;
    }

    private function createReservation(DoorFrameConfiguration $config, $desired, ?User $requestedBy): JobReservation
    {
        return DB::transaction(function () use ($config, $desired, $requestedBy) {
            $reservation = JobReservation::create([
                'business_job_id' => $config->business_job_id,
                'job_number' => $config->businessJob->job_number,
                'job_name' => $config->businessJob->job_name,
                'requested_by' => $requestedBy?->name ?? 'Configurator',
                'requested_by_id' => $requestedBy?->id,
                'status' => 'active',
                'notes' => "Auto-created from door/frame configuration #{$config->id}.",
            ]);

            foreach ($desired as $productId => $qty) {
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

    /**
     * Diffs the desired product/quantity map against the reservation's
     * current items, mutating one JobReservationItem row at a time (never a
     * bulk delete-then-reinsert) so its saved/deleted model hooks keep
     * Product::quantity_committed/quantity_available correctly in sync —
     * the same per-item CRUD convention JobReservationController's
     * addItem/updateItem/removeItem already use.
     */
    private function syncReservation(DoorFrameConfiguration $config, $desired, array &$warnings): JobReservation
    {
        $reservation = $config->jobReservation()->with('items')->first();

        return DB::transaction(function () use ($reservation, $desired, &$warnings) {
            $existingByProduct = $reservation->items->keyBy('product_id');

            foreach ($desired as $productId => $qty) {
                $item = $existingByProduct->get($productId);

                if (! $item) {
                    JobReservationItem::create([
                        'reservation_id' => $reservation->id,
                        'product_id' => $productId,
                        'requested_qty' => $qty,
                        'committed_qty' => $qty,
                        'consumed_qty' => 0,
                    ]);

                    continue;
                }

                $newQty = max((float) $qty, (float) $item->consumed_qty);
                if ($newQty < (float) $qty) {
                    $warnings[] = "Could not reduce product #{$productId} below the {$item->consumed_qty} already consumed.";
                }

                if ((float) $item->requested_qty !== $newQty || (float) $item->committed_qty !== $newQty) {
                    $item->requested_qty = $newQty;
                    $item->committed_qty = $newQty;
                    $item->save();
                }
            }

            foreach ($existingByProduct as $productId => $item) {
                if ($desired->has($productId)) {
                    continue;
                }

                if ((float) $item->consumed_qty > 0) {
                    $warnings[] = "Kept product #{$productId} reserved — already partially consumed.";

                    continue;
                }

                $item->delete();
            }

            return $reservation;
        });
    }
}
