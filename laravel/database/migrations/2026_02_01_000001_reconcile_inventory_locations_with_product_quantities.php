<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Reconcile inventory_locations.quantity to match products.quantity_on_hand
     * This ensures location quantities reflect the current product quantities
     */
    public function up(): void
    {
        // Get all products with their inventory locations
        $products = DB::table('products')
            ->whereNull('deleted_at')
            ->get();

        foreach ($products as $product) {
            // Get all inventory locations for this product
            $inventoryLocations = DB::table('inventory_locations')
                ->where('product_id', $product->id)
                ->whereNull('deleted_at')
                ->get();

            if ($inventoryLocations->count() === 0) {
                // Product has no inventory locations - create one at Unassigned location
                $unassignedLocationId = DB::table('storage_locations')
                    ->where('code', 'UNASSIGNED')
                    ->value('id');

                if ($unassignedLocationId && $product->quantity_on_hand > 0) {
                    DB::table('inventory_locations')->insert([
                        'product_id' => $product->id,
                        'storage_location_id' => $unassignedLocationId,
                        'quantity' => $product->quantity_on_hand,
                        'quantity_committed' => 0,
                        'is_primary' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } elseif ($inventoryLocations->count() === 1) {
                // Product has one location - update it to match quantity_on_hand
                $location = $inventoryLocations->first();

                DB::table('inventory_locations')
                    ->where('id', $location->id)
                    ->update([
                        'quantity' => $product->quantity_on_hand,
                        'updated_at' => now(),
                    ]);
            } else {
                // Product has multiple locations
                // Calculate total across all locations
                $totalLocationQty = $inventoryLocations->sum('quantity');

                // If totals don't match, proportionally distribute product.quantity_on_hand
                if ($totalLocationQty != $product->quantity_on_hand && $totalLocationQty > 0) {
                    $ratio = $product->quantity_on_hand / $totalLocationQty;

                    foreach ($inventoryLocations as $location) {
                        $newQty = (int) round($location->quantity * $ratio);

                        DB::table('inventory_locations')
                            ->where('id', $location->id)
                            ->update([
                                'quantity' => $newQty,
                                'updated_at' => now(),
                            ]);
                    }
                } elseif ($totalLocationQty == 0 && $product->quantity_on_hand > 0) {
                    // All locations have 0, but product has quantity
                    // Put all quantity in the primary location (or first location)
                    $primaryLocation = $inventoryLocations->where('is_primary', true)->first()
                        ?? $inventoryLocations->first();

                    DB::table('inventory_locations')
                        ->where('id', $primaryLocation->id)
                        ->update([
                            'quantity' => $product->quantity_on_hand,
                            'updated_at' => now(),
                        ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback - this is a data reconciliation
        // Reverting would require storing original values
    }
};
