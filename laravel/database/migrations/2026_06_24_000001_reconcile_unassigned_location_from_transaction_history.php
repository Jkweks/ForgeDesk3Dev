<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data reconciliation: find inventory that was received (per transaction history)
 * but never written to an inventory_locations row — a side-effect of the bug where
 * products with no existing location had their receipt quantity applied only to the
 * products.quantity_on_hand column and not to inventory_locations.
 *
 * Strategy:
 *   net_from_history  = SUM(inventory_transactions.quantity) for the product
 *                       (receipts stored positive, issues/shipments stored negative)
 *   location_total    = SUM(inventory_locations.quantity) for the product
 *   gap               = net_from_history - location_total
 *
 * If gap > 0 the quantity exists in history but has no location record.
 * We write that gap into (or add it to) the product's UNASSIGNED inventory_location.
 * products.quantity_on_hand is then recalculated from the location table so it
 * is always derived from the source of truth.
 *
 * Safe to re-run: only products with a positive gap are touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $unassignedStorageId = DB::table('storage_locations')
            ->where('code', 'UNASSIGNED')
            ->value('id');

        if (!$unassignedStorageId) {
            echo "  UNASSIGNED storage location not found — skipping reconciliation.\n";
            return;
        }

        // Net quantity per product from the full transaction ledger
        $transactionTotals = DB::table('inventory_transactions')
            ->selectRaw('product_id, SUM(quantity) as net_qty')
            ->groupBy('product_id')
            ->pluck('net_qty', 'product_id');

        // Current total held in inventory_locations per product
        $locationTotals = DB::table('inventory_locations')
            ->whereNull('deleted_at')
            ->selectRaw('product_id, SUM(quantity) as total_qty')
            ->groupBy('product_id')
            ->pluck('total_qty', 'product_id');

        $fixed   = 0;
        $skipped = 0;

        foreach ($transactionTotals as $productId => $netFromHistory) {
            $netFromHistory = (int) round($netFromHistory);
            $locationTotal  = (int) round($locationTotals[$productId] ?? 0);
            $gap            = $netFromHistory - $locationTotal;

            if ($gap <= 0) {
                $skipped++;
                continue;
            }

            // Find or create this product's UNASSIGNED inventory_location
            $existing = DB::table('inventory_locations')
                ->where('product_id', $productId)
                ->where('storage_location_id', $unassignedStorageId)
                ->whereNull('deleted_at')
                ->first();

            if ($existing) {
                DB::table('inventory_locations')
                    ->where('id', $existing->id)
                    ->update([
                        'quantity'   => $existing->quantity + $gap,
                        'updated_at' => now(),
                    ]);
            } else {
                // Check if the product has any locations at all to decide is_primary
                $hasOtherLocations = DB::table('inventory_locations')
                    ->where('product_id', $productId)
                    ->whereNull('deleted_at')
                    ->exists();

                DB::table('inventory_locations')->insert([
                    'product_id'         => $productId,
                    'storage_location_id' => $unassignedStorageId,
                    'quantity'           => $gap,
                    'quantity_committed' => 0,
                    'is_primary'         => !$hasOtherLocations,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }

            // Recalculate quantity_on_hand from the now-correct location table
            $newOnHand = DB::table('inventory_locations')
                ->where('product_id', $productId)
                ->whereNull('deleted_at')
                ->sum('quantity');

            DB::table('products')
                ->where('id', $productId)
                ->update([
                    'quantity_on_hand' => $newOnHand,
                    'updated_at'       => now(),
                ]);

            $fixed++;
        }

        echo "  Reconciliation complete: {$fixed} product(s) fixed, {$skipped} already balanced.\n";
    }

    public function down(): void
    {
        // No rollback — a down() here would require storing every original value.
        // If you need to undo this, restore from a DB backup taken before running up().
    }
};
