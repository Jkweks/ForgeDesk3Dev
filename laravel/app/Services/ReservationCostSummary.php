<?php

namespace App\Services;

use App\Models\JobReservation;

/**
 * Material cost of a job reservation: each line's requested quantity x the product's net price
 * (Product::netUnitPrice — per piece for pack items). Items with no net price count as $0 and are
 * reported in `missing_price` so a total is never silently presented as complete.
 */
class ReservationCostSummary
{
    /**
     * @return array{reservation: array<string, mixed>, rows: array<int, array<string, mixed>>, total: float, consumed_total: float, missing_price: int}
     */
    public function summarize(JobReservation $reservation): array
    {
        $reservation->loadMissing('items.product');

        $total = 0.0;
        $consumedTotal = 0.0;
        $missing = 0;

        $rows = $reservation->items->filter(fn ($i) => $i->product)->map(function ($item) use (&$total, &$consumedTotal, &$missing) {
            $p = $item->product;
            $unit = $p->netUnitPrice();
            $qty = (float) $item->requested_qty;
            $ext = $unit !== null ? round($qty * $unit, 2) : null;
            $consumedExt = $unit !== null ? round((float) $item->consumed_qty * $unit, 2) : null;
            if ($unit === null || $unit <= 0) {
                $missing++;
            }
            $total += $ext ?? 0;
            $consumedTotal += $consumedExt ?? 0;
            $packSize = $p->hasPackSize() ? (int) $p->pack_size : 1;

            return [
                'part_number' => $p->part_number ?: $p->sku,
                'finish' => $p->finish,
                'description' => $p->description,
                'quantity' => round($qty, 2),
                'consumed_qty' => round((float) $item->consumed_qty, 2),
                'net_price' => $unit !== null ? round($unit, 4) : null,
                'pack_size' => $packSize,
                'pack_net_price' => $packSize > 1 ? (float) $p->net_cost : null,
                'extended' => $ext,
                'consumed_extended' => $consumedExt,
            ];
        })->sortBy('part_number')->values()->all();

        return [
            'reservation' => [
                'id' => $reservation->id,
                'job_number' => $reservation->job_number,
                'release_number' => $reservation->release_number,
                'job_name' => $reservation->job_name,
                'status' => $reservation->status,
            ],
            'rows' => $rows,
            'total' => round($total, 2),
            'consumed_total' => round($consumedTotal, 2),
            'missing_price' => $missing,
        ];
    }
}
