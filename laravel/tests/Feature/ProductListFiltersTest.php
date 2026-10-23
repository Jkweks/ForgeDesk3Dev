<?php

namespace Tests\Feature;

use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Product $placed;

    private Product $unplaced;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);

        $supplier = Supplier::create(['name' => 'S']);
        $this->placed = Product::create(['sku' => 'PLACED', 'description' => 'Has a bin', 'supplier_id' => $supplier->id, 'is_active' => true, 'status' => 'critical']);
        $this->unplaced = Product::create(['sku' => 'LOOSE', 'description' => 'No bin', 'supplier_id' => $supplier->id, 'is_active' => true, 'status' => 'critical']);

        $loc = StorageLocation::create(['name' => 'A.1', 'type' => 'bin', 'is_active' => true]);
        InventoryLocation::create(['product_id' => $this->placed->id, 'storage_location_id' => $loc->id, 'quantity' => 5, 'quantity_committed' => 0, 'is_primary' => true]);
    }

    public function test_all_tab_can_filter_to_products_without_a_location(): void
    {
        $res = $this->getJson('/api/v1/dashboard?no_location=1')->assertOk();
        $this->assertSame(['LOOSE'], array_column($res->json('inventory.data'), 'sku'));

        $both = $this->getJson('/api/v1/dashboard')->assertOk()->json('inventory.data');
        $this->assertCount(2, $both);
    }

    public function test_status_tabs_filter_and_include_location_names(): void
    {
        $res = $this->getJson('/api/v1/dashboard/inventory/critical')->assertOk();
        $placed = collect($res->json('data'))->firstWhere('sku', 'PLACED');
        $this->assertSame('A.1', $placed['inventory_locations'][0]['storage_location']['name']);

        $only = $this->getJson('/api/v1/dashboard/inventory/critical?no_location=1')->assertOk()->json('data');
        $this->assertSame(['LOOSE'], array_column($only, 'sku'));
    }
}
