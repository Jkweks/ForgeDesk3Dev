<?php

namespace App\Services;

use App\Models\InventoryLocation;
use App\Models\InventoryTransaction;
use App\Models\Product;

/**
 * Takes stock out of a product the canonical way: locations are the source of truth (primary first,
 * then the others in order; any remainder past zero goes negative on the primary), product totals are
 * recalculated from them, and an inventory transaction records it. Same behaviour as completing a
 * reservation (JobReservationController::complete), for callers that consume stock outside that screen.
 */
class InventoryDeductor
{
    public function deduct(Product $product, float $quantity, string $reference, string $notes, ?int $userId = null): void
    {
        if ($quantity <= 0) {
            return;
        }

        $stockBefore = $product->quantity_on_hand;
        $remaining = $quantity;

        $locations = InventoryLocation::where('product_id', $product->id)
            ->orderBy('is_primary', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($locations as $location) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, max(0, (float) $location->quantity));
            if ($take > 0) {
                $location->quantity -= $take;
                $location->save();
                $remaining -= $take;
            }
        }

        // More taken than any location held: the primary (else first) location absorbs it, going negative.
        if ($remaining > 0 && $locations->isNotEmpty()) {
            $absorb = $locations->firstWhere('is_primary', true) ?? $locations->first();
            $absorb->quantity -= $remaining;
            $absorb->save();
        }

        $product->recalculateQuantitiesFromLocations();

        InventoryTransaction::create([
            'product_id' => $product->id,
            'type' => 'fulfillment',
            'quantity' => -$quantity,
            'quantity_before' => $stockBefore,
            'quantity_after' => $product->quantity_on_hand,
            'reference_number' => $reference,
            'notes' => $notes,
            'transaction_date' => now(),
            'user_id' => $userId,
        ]);
    }
}
