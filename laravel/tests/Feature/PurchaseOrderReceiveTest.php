<?php

namespace Tests\Feature;

use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression coverage for PurchaseOrderController::receive() previously bumping
 * quantity_on_hand directly instead of deriving it from inventory_locations —
 * meaning any pre-existing drift between the two compounded instead of
 * self-correcting. It now updates the location then calls
 * recalculateQuantitiesFromLocations().
 */
class PurchaseOrderReceiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_receive_self_corrects_pre_existing_drift_instead_of_compounding_it(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $storageLocation = StorageLocation::create([
            'name' => 'Bin A1',
            'code' => 'BIN-A1',
        ]);

        $product = Product::create([
            'sku' => 'PO-TEST-001',
            'description' => 'PO test product',
            // Deliberately drifted: the direct field says 100, but the real
            // location total (below) is only 40 — simulating pre-existing drift.
            'quantity_on_hand' => 100,
        ]);

        InventoryLocation::create([
            'product_id' => $product->id,
            'storage_location_id' => $storageLocation->id,
            'quantity' => 40,
            'is_primary' => true,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-1',
            'status' => 'approved',
            'order_date' => now()->toDateString(),
        ]);

        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity_ordered' => 10,
            'unit_cost' => 5,
            'total_cost' => 50,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson("/api/v1/purchase-orders/{$po->id}/receive", [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 10, 'storage_location_id' => $storageLocation->id],
            ],
        ]);

        $response->assertOk();

        $product->refresh();
        // Self-corrected from the drifted 100 to location-sum (40) + received (10) = 50,
        // not the old buggy 100 + 10 = 110.
        $this->assertEquals(50, $product->quantity_on_hand);

        $location = InventoryLocation::where('product_id', $product->id)->first();
        $this->assertEquals(50, $location->quantity);
    }

    public function test_receive_creates_unassigned_location_when_product_has_none(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        // The "Unassigned" storage location is already seeded by
        // 2026_01_31_000001_enforce_hierarchical_locations_on_inventory.php.
        $product = Product::create([
            'sku' => 'PO-TEST-002',
            'description' => 'PO test product 2',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-2',
            'status' => 'approved',
            'order_date' => now()->toDateString(),
        ]);

        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity_ordered' => 5,
            'unit_cost' => 5,
            'total_cost' => 25,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson("/api/v1/purchase-orders/{$po->id}/receive", [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 5],
            ],
        ]);

        $response->assertOk();

        $product->refresh();
        $this->assertEquals(5, $product->quantity_on_hand);
        $this->assertDatabaseHas('inventory_locations', [
            'product_id' => $product->id,
            'quantity' => 5,
            'is_primary' => true,
        ]);
    }
}
