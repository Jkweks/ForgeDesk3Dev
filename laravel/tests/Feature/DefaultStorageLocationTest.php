<?php

namespace Tests\Feature;

use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Stock with nowhere to live falls back to the DEFAULT location, even if it was deleted. */
class DefaultStorageLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_the_default_location_when_missing(): void
    {
        StorageLocation::withTrashed()->where('code', 'DEFAULT')->forceDelete();

        $loc = StorageLocation::defaultLocation();

        $this->assertSame('DEFAULT', $loc->code);
        $this->assertTrue($loc->exists);
    }

    public function test_restores_a_soft_deleted_default_location_instead_of_duplicating(): void
    {
        $first = StorageLocation::defaultLocation();
        $first->delete();
        $this->assertSoftDeleted('storage_locations', ['id' => $first->id]);

        $again = StorageLocation::defaultLocation();

        $this->assertSame($first->id, $again->id);
        $this->assertNull($again->fresh()->deleted_at);
        $this->assertSame(1, StorageLocation::withTrashed()->where('code', 'DEFAULT')->count());
    }

    public function test_po_receive_succeeds_when_default_location_was_soft_deleted(): void
    {
        // Regression: PO receive 500'd with a null storage_location_id when the fallback was soft-deleted.
        StorageLocation::defaultLocation()->delete();

        $product = Product::create([
            'sku' => 'DEF-001',
            'description' => 'Default location test',
            'supplier_id' => Supplier::create(['name' => 'Default Test Supplier'])->id,
        ]);
        $po = PurchaseOrder::create(['po_number' => 'PO-DEF', 'status' => 'approved', 'order_date' => now()->toDateString()]);
        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id, 'product_id' => $product->id,
            'quantity_ordered' => 3, 'unit_cost' => 1, 'total_cost' => 3,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->postJson("/api/v1/purchase-orders/{$po->id}/receive", [
            'items' => [['item_id' => $item->id, 'quantity' => 3]],
        ])->assertOk();

        $loc = InventoryLocation::where('product_id', $product->id)->first();
        $this->assertNotNull($loc->storage_location_id);
        $this->assertSame('DEFAULT', StorageLocation::find($loc->storage_location_id)->code);
        $this->assertEquals(3, $product->fresh()->quantity_on_hand);
    }
}
