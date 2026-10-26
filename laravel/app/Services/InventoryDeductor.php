<?php

namespace App\Services;

use App\Models\InventoryLocation;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\StorageLocation;

/**
 * Takes stock out of a product the canonical way: locations are the source of truth (primary first,
 * then the others in order; any remainder past zero goes negative on the primary), product totals are
 * recalculated from them, and an inventory transaction records it. Same behaviour as completing a
 * reservation (JobReservationController::complete), for callers that consume stock outside that screen.
 */
class InventoryDeductor
{
    /**
     * Put stock on the product's primary bin (creating a primary bin at the default location if it has none)
     * and recalculate the product total from the bins. No ledger row: use adjust() for a recorded movement.
     */
    public function seed(Product $product, float $quantity): void
    {
        if ($quantity == 0) {
            return;
        }

        $bin = InventoryLocation::where('product_id', $product->id)->orderBy('is_primary', 'desc')->orderBy('id')->lockForUpdate()->first()
            ?? new InventoryLocation([
                'product_id' => $product->id,
                'storage_location_id' => StorageLocation::defaultLocation()->id,
                'quantity' => 0,
                'quantity_committed' => 0,
                'is_primary' => true,
            ]);
        $bin->quantity = (float) $bin->quantity + $quantity;
        $bin->save();

        $product->recalculateQuantitiesFromLocations();
    }

    /**
     * A recorded stock movement of any sign: positive adds to the primary bin, negative draws down the bins
     * (see deduct()). The product total is always derived from the bins.
     */
    public function adjust(Product $product, float $delta, string $type, ?string $reference = null, ?string $notes = null, ?int $userId = null): void
    {
        if ($delta == 0) {
            return;
        }
        if ($delta < 0) {
            $this->deduct($product, -$delta, (string) $reference, (string) $notes, $userId, $type);

            return;
        }

        $before = (float) ($product->quantity_on_hand ?? 0);
        $this->seed($product, $delta);

        InventoryTransaction::create([
            'product_id' => $product->id,
            'type' => $type,
            'quantity' => $delta,
            'quantity_before' => $before,
            'quantity_after' => $product->quantity_on_hand,
            'reference_number' => $reference,
            'notes' => $notes,
            'transaction_date' => now(),
            'user_id' => $userId,
        ]);
    }

    public function deduct(Product $product, float $quantity, string $reference, string $notes, ?int $userId = null, string $type = 'fulfillment'): void
    {
        if ($quantity <= 0) {
            return;
        }

        $stockBefore = (float) ($product->quantity_on_hand ?? 0);
        $remaining = $quantity;

        $locations = InventoryLocation::where('product_id', $product->id)
            ->orderBy('is_primary', 'desc')
            ->orderBy('id', 'asc')
            ->lockForUpdate() // concurrent deductions apply to current bin quantities (no-op outside a transaction)
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
            'type' => $type,
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
