<?php

namespace Tests\Feature;

use App\Models\InventoryLocation;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryDeductor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * products.quantity_on_hand is owned by the database: a trigger on inventory_locations keeps it equal to
 * the sum of the live bins, and the model refuses direct writes that disagree with the bins.
 */
class InventoryQuantityTriggerTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $sku = 'TRG-1'): Product
    {
        return Product::create(['sku' => $sku, 'description' => 'Trigger test', 'supplier_id' => Supplier::firstOrCreate(['name' => 'Trigger Supplier'])->id]);
    }

    private function bin(Product $p, float $qty, string $code): InventoryLocation
    {
        $loc = StorageLocation::create(['name' => $code, 'code' => $code, 'type' => 'rack']);

        return InventoryLocation::create(['product_id' => $p->id, 'storage_location_id' => $loc->id, 'quantity' => $qty, 'is_primary' => $code === 'A']);
    }

    private function onHand(Product $p): float
    {
        return (float) DB::table('products')->where('id', $p->id)->value('quantity_on_hand');
    }

    public function test_trigger_tracks_insert_update_soft_delete_and_delete_of_bins(): void
    {
        $p = $this->product();
        $a = $this->bin($p, 6, 'A');
        $b = $this->bin($p, 4, 'B');
        $this->assertEquals(10, $this->onHand($p));

        $a->update(['quantity' => 1]);
        $this->assertEquals(5, $this->onHand($p));

        $b->delete(); // soft delete
        $this->assertEquals(1, $this->onHand($p));

        $a->forceDelete();
        $this->assertEquals(0, $this->onHand($p));
    }

    public function test_raw_sql_changes_to_a_bin_are_also_tracked(): void
    {
        $p = $this->product();
        $this->bin($p, 3, 'A');

        DB::table('inventory_locations')->where('product_id', $p->id)->update(['quantity' => 9]);

        $this->assertEquals(9, $this->onHand($p));
    }

    public function test_a_direct_write_that_disagrees_with_the_bins_is_ignored(): void
    {
        $p = $this->product();
        $this->bin($p, 7, 'A');

        $p->fresh()->update(['quantity_on_hand' => 500]);

        $this->assertEquals(7, $this->onHand($p));
    }

    public function test_adjust_adds_to_the_primary_bin_and_logs_a_transaction(): void
    {
        $p = $this->product();
        $svc = app(InventoryDeductor::class);

        $svc->adjust($p, 5, 'receipt', 'REF', 'got some');   // no bin yet: created at the default location
        $svc->adjust($p, -2, 'adjustment', 'REF2', 'lost');

        $this->assertEquals(3, $this->onHand($p));
        $this->assertEquals(3, (float) InventoryLocation::where('product_id', $p->id)->sum('quantity'));
        $this->assertEquals([5.0, -2.0], InventoryTransaction::where('product_id', $p->id)->orderBy('id')->pluck('quantity')->map(fn ($q) => (float) $q)->all());
    }

    public function test_creating_a_product_with_stock_creates_its_bin(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($user, ['*']);
        $supplier = Supplier::create(['name' => 'API Supplier']);

        $res = $this->postJson('/api/v1/products', [
            'sku' => 'API-NEW', 'description' => 'New', 'unit_cost' => 1, 'quantity_on_hand' => 12,
            'minimum_quantity' => 0, 'unit_of_measure' => 'EA', 'supplier_id' => $supplier->id,
        ])->assertCreated();

        $product = Product::find($res->json('id'));
        $this->assertEquals(12, (float) InventoryLocation::where('product_id', $product->id)->sum('quantity'));
        $this->assertEquals(12, $this->onHand($product));
    }

    public function test_check_totals_command_reports_and_fixes_drift(): void
    {
        $p = $this->product();
        $this->bin($p, 4, 'A');
        DB::table('products')->where('id', $p->id)->update(['quantity_on_hand' => 99]); // simulate drift

        $this->artisan('inventory:check-totals')->assertExitCode(1);
        $this->artisan('inventory:check-totals --fix')->assertExitCode(0);
        $this->assertEquals(4, $this->onHand($p));
    }
}
