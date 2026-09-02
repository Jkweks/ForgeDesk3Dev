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

class PurchaseOrderPdfTest extends TestCase
{
    use RefreshDatabase;

    private function seedPo(array $poAttr = []): PurchaseOrder
    {
        $supplier = Supplier::create([
            'name' => 'Acme Extrusions',
            'contact_name' => 'Pat Buyer',
            'contact_phone' => '555-0100',
            'fax' => '555-0199',
            'address' => '9 Mill Rd',
            'city' => 'Akron',
            'state' => 'OH',
            'zip' => '44301',
        ]);

        $packProduct = Product::create([
            'sku' => 'AA100-C2',
            'part_number' => 'AA100',
            'finish' => 'C2',
            'description' => 'Snap cover',
            'pack_size' => 12,
            'net_cost' => 4.25,
            'unit_cost' => 9.99,
        ]);

        $po = PurchaseOrder::create(array_merge([
            'po_number' => 'PO-PDF-1',
            'supplier_id' => $supplier->id,
            'status' => 'approved',
            'order_date' => now()->toDateString(),
        ], $poAttr));

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $packProduct->id,
            'quantity_ordered' => 10,
            'unit_cost' => 4.25, // captured net price
            'total_cost' => 42.50,
        ]);

        return $po;
    }

    public function test_pdf_downloads_for_any_supplier(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);

        $po = $this->seedPo();

        $res = $this->get("/api/v1/purchase-orders/{$po->id}/pdf");

        $res->assertOk();
        $res->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('PO_PO-PDF-1.pdf', $res->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_pdf_uses_primary_company_location_and_selected_ship_to(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);

        // Seeded primary + a second location to ship to.
        CompanyLocation::query()->update(['name' => 'HQ Plant', 'city' => 'Detroit', 'state' => 'MI']);
        $ship = CompanyLocation::create(['name' => 'East Yard', 'is_primary' => false, 'city' => 'Cleveland']);

        $po = $this->seedPo(['ship_to_location_id' => $ship->id]);

        $this->get("/api/v1/purchase-orders/{$po->id}/pdf")->assertOk();
    }

    public function test_store_and_update_accept_ship_to_location_id(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);

        $ship = CompanyLocation::create(['name' => 'Dock 5', 'is_primary' => false]);
        $supplier = Supplier::create(['name' => 'Bolt Co']);
        $product = Product::create(['sku' => 'B1', 'description' => 'Bolt', 'net_cost' => 1]);

        $created = $this->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'ship_to_location_id' => $ship->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'unit_cost' => 1],
            ],
        ])->assertCreated();

        $poId = $created->json('purchase_order.id');
        $this->assertDatabaseHas('purchase_orders', ['id' => $poId, 'ship_to_location_id' => $ship->id]);

        $this->putJson("/api/v1/purchase-orders/{$poId}", ['ship_to_location_id' => null])->assertOk();
        $this->assertDatabaseHas('purchase_orders', ['id' => $poId, 'ship_to_location_id' => null]);
    }
}
