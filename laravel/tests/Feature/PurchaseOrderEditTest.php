<?php

namespace Tests\Feature;

use App\Models\CompanyLocation;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A purchase order stays fully editable — header, addresses, shipping, and line
 * items — until it reaches "approved".
 */
class PurchaseOrderEditTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->supplier = Supplier::create(['name' => 'Acme']);
        $this->product = Product::create([
            'sku' => 'W1', 'description' => 'Widget', 'net_cost' => 2, 'on_order_qty' => 0,
            'supplier_id' => $this->supplier->id,
        ]);
    }

    private function poWithItem(string $status, int $qty = 4, float $cost = 2): array
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-'.$status.'-'.uniqid(),
            'supplier_id' => $this->supplier->id,
            'status' => $status,
            'order_date' => now()->toDateString(),
            'total_amount' => $qty * $cost,
        ]);
        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity_ordered' => $qty,
            'unit_cost' => $cost,
            'total_cost' => $qty * $cost,
        ]);
        $this->product->update(['on_order_qty' => $qty]);

        return [$po, $item];
    }

    public function test_submitted_po_header_addresses_and_shipping_are_editable(): void
    {
        $ship = CompanyLocation::create(['name' => 'Dock 9', 'is_primary' => false]);
        [$po] = $this->poWithItem('submitted');

        $this->putJson("/api/v1/purchase-orders/{$po->id}", [
            'expected_date' => now()->addWeek()->toDateString(),
            'ship_to_location_id' => $ship->id,
            'ship_to' => 'Gate code 4432',
            'contact_name' => 'Dana Buyer',
            'contact_email' => 'dana@example.com',
            'contact_phone' => '555-0142',
            'notes' => 'Rush',
        ])->assertOk();

        $po->refresh();
        $this->assertSame($ship->id, $po->ship_to_location_id);
        $this->assertSame('Gate code 4432', $po->ship_to);
        $this->assertSame('Dana Buyer', $po->contact_name);
        $this->assertSame('Rush', $po->notes);
    }

    public function test_submitted_po_line_items_can_be_added_edited_and_removed(): void
    {
        [$po, $item] = $this->poWithItem('submitted', 4, 2.00);

        // edit qty + cost
        $this->patchJson("/api/v1/purchase-orders/{$po->id}/items/{$item->id}", [
            'quantity' => 10, 'unit_cost' => 2.50,
        ])->assertOk();

        $item->refresh();
        $this->assertSame(10, $item->quantity_ordered);
        $this->assertEquals(25.00, $item->total_cost);
        $this->assertEquals(10, $this->product->fresh()->on_order_qty); // 4 -> 10
        $this->assertEquals(25.00, $po->fresh()->total_amount);

        // add a second line
        $p2 = Product::create(['sku' => 'W2', 'description' => 'Widget 2', 'net_cost' => 1, 'supplier_id' => $this->supplier->id]);
        $this->postJson("/api/v1/purchase-orders/{$po->id}/items", [
            'product_id' => $p2->id, 'quantity' => 3, 'unit_cost' => 1,
        ])->assertCreated();
        $this->assertEquals(28.00, $po->fresh()->total_amount);

        // remove the first line
        $this->deleteJson("/api/v1/purchase-orders/{$po->id}/items/{$item->id}")->assertOk();
        $this->assertEquals(3.00, $po->fresh()->total_amount);
        $this->assertEquals(0, $this->product->fresh()->on_order_qty);
    }

    public function test_approved_po_rejects_header_and_line_item_edits(): void
    {
        [$po, $item] = $this->poWithItem('approved');

        $this->putJson("/api/v1/purchase-orders/{$po->id}", ['notes' => 'nope'])->assertStatus(422);
        $this->patchJson("/api/v1/purchase-orders/{$po->id}/items/{$item->id}", ['quantity' => 9])->assertStatus(422);
        $this->postJson("/api/v1/purchase-orders/{$po->id}/items", [
            'product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 1,
        ])->assertStatus(422);
        $this->deleteJson("/api/v1/purchase-orders/{$po->id}/items/{$item->id}")->assertStatus(422);
    }

    public function test_line_item_quantity_cannot_drop_below_amount_received(): void
    {
        [$po, $item] = $this->poWithItem('submitted', 6);
        $item->update(['quantity_received' => 4]);

        $this->patchJson("/api/v1/purchase-orders/{$po->id}/items/{$item->id}", ['quantity' => 2])
            ->assertStatus(422);
    }
}
