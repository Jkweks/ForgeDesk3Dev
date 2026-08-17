<?php

namespace Tests\Feature;

use App\Models\CycleCountItem;
use App\Models\CycleCountSession;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for CycleCountItem's legacy (no location_id) branch,
 * which used to write quantity_on_hand directly. It now applies the variance
 * to the product's primary inventory location (or creates an "Unassigned" one)
 * and calls recalculateQuantitiesFromLocations(), keeping inventory_locations
 * as the source of truth.
 */
class CycleCountItemApproveVarianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_branch_creates_location_when_product_has_none(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $product = Product::create([
            'sku' => 'CC-TEST-001',
            'description' => 'Cycle count test product',
        ]);

        $session = CycleCountSession::create([
            'session_number' => 'CC-1',
            'scheduled_date' => now()->toDateString(),
            'status' => 'in_progress',
        ]);

        $item = CycleCountItem::create([
            'session_id' => $session->id,
            'product_id' => $product->id,
            'location_id' => null,
            'system_quantity' => 10,
            'counted_quantity' => 15,
            'variance' => 5,
            'variance_status' => 'requires_review',
        ]);

        $transaction = $item->approveVariance($user->id);

        $this->assertNotNull($transaction);
        $product->refresh();
        $this->assertEquals(15, $product->quantity_on_hand);
        $this->assertDatabaseHas('inventory_locations', [
            'product_id' => $product->id,
            'quantity' => 15,
            'is_primary' => true,
        ]);
        $this->assertEquals(15, $transaction->quantity_after);
    }

    public function test_legacy_branch_applies_variance_delta_to_existing_primary_location(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $storageLocation = StorageLocation::create([
            'name' => 'Bin B2',
            'code' => 'BIN-B2',
        ]);

        $product = Product::create([
            'sku' => 'CC-TEST-002',
            'description' => 'Cycle count test product 2',
        ]);

        // Product already has stock elsewhere; the legacy count only covers part of it.
        InventoryLocation::create([
            'product_id' => $product->id,
            'storage_location_id' => $storageLocation->id,
            'quantity' => 20,
            'is_primary' => true,
        ]);
        $product->recalculateQuantitiesFromLocations();

        $session = CycleCountSession::create([
            'session_number' => 'CC-2',
            'scheduled_date' => now()->toDateString(),
            'status' => 'in_progress',
        ]);

        // system_quantity snapshot matches current on-hand (20); counted is 25 → variance +5.
        $item = CycleCountItem::create([
            'session_id' => $session->id,
            'product_id' => $product->id,
            'location_id' => null,
            'system_quantity' => 20,
            'counted_quantity' => 25,
            'variance' => 5,
            'variance_status' => 'requires_review',
        ]);

        $item->approveVariance($user->id);

        $product->refresh();
        // Self-corrected total = existing location (20) + variance (5) = 25, applied
        // via the primary location rather than an unchecked direct write.
        $this->assertEquals(25, $product->quantity_on_hand);

        $location = InventoryLocation::where('product_id', $product->id)->first();
        $this->assertEquals(25, $location->quantity);
    }
}
