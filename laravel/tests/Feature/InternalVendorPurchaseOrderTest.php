<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** PM Suite integration groundwork: internal vendors and PO types (docs/plans/pm-suite-integration.md). */
class InternalVendorPurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
    }

    private function product(Supplier $supplier): Product
    {
        return Product::create([
            'sku' => 'T-'.$supplier->id, 'description' => 'Test', 'supplier_id' => $supplier->id,
            'quantity_on_hand' => 0, 'on_order_qty' => 0,
        ]);
    }

    private function createPo(Supplier $supplier, Product $product, int $qty = 5): PurchaseOrder
    {
        $id = $this->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => $qty, 'unit_cost' => 10]],
        ])->assertCreated()->json('purchase_order.id');

        return PurchaseOrder::findOrFail($id);
    }

    public function test_fabrication_vendor_is_seeded_as_internal(): void
    {
        $this->assertTrue(Supplier::where('name', 'Vos Glass - Fabrication')->firstOrFail()->is_internal);
    }

    public function test_po_type_follows_vendor(): void
    {
        $stockVendor = Supplier::create(['name' => 'ACME', 'is_active' => true]);
        $internal = Supplier::create(['name' => 'Shop', 'is_active' => true, 'is_internal' => true]);
        $p = $this->product($stockVendor);

        $this->assertSame('stock', $this->createPo($stockVendor, $p)->type);
        $this->assertSame('internal', $this->createPo($internal, $p)->type);
    }

    public function test_internal_po_does_not_touch_on_order_and_cannot_be_received(): void
    {
        $internal = Supplier::create(['name' => 'Shop', 'is_active' => true, 'is_internal' => true]);
        $p = $this->product($internal);

        $po = $this->createPo($internal, $p, 7);

        $this->assertSame(0, (int) $p->fresh()->on_order_qty);
        $po->update(['status' => 'approved']);
        $this->postJson("/api/v1/purchase-orders/{$po->id}/receive", [
            'items' => [['item_id' => $po->items()->first()->id, 'quantity' => 1]],
        ])->assertStatus(422);
        $this->assertSame(0, (int) $p->fresh()->quantity_on_hand);
    }

    public function test_stock_po_still_moves_on_order_and_stats_exclude_internal(): void
    {
        $stockVendor = Supplier::create(['name' => 'ACME', 'is_active' => true]);
        $internal = Supplier::create(['name' => 'Shop', 'is_active' => true, 'is_internal' => true]);
        $p = $this->product($stockVendor);

        $this->createPo($stockVendor, $p, 5);
        $this->createPo($internal, $p, 9);

        $this->assertSame(5, (int) $p->fresh()->on_order_qty);
        $this->getJson('/api/v1/purchase-orders-statistics')->assertOk()->assertJsonPath('draft', 1);
    }

    public function test_internal_flag_locked_once_vendor_has_pos(): void
    {
        $v = Supplier::create(['name' => 'ACME', 'is_active' => true]);
        $p = $this->product($v);

        $this->putJson("/api/v1/suppliers/{$v->id}", ['name' => 'ACME', 'is_internal' => true])->assertOk();
        $this->assertTrue($v->fresh()->is_internal);

        $this->createPo($v, $p);
        $this->putJson("/api/v1/suppliers/{$v->id}", ['name' => 'ACME', 'is_internal' => false])->assertStatus(422);
        $this->assertTrue($v->fresh()->is_internal);
    }
}
