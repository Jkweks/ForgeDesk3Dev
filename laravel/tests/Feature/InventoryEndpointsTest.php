<?php

namespace Tests\Feature;

use App\Models\InventoryLocation;
use App\Models\InventoryTransaction;
use App\Models\Machine;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Every path that changes stock must leave products.quantity_on_hand equal to the bins and write a ledger row.
 */
class InventoryEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private InventoryLocation $binA;

    private InventoryLocation $binB;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->product = Product::create(['sku' => 'INV-1', 'description' => 'Inv test', 'supplier_id' => Supplier::create(['name' => 'S'])->id]);
        $a = StorageLocation::create(['name' => 'A', 'code' => 'A', 'type' => 'rack']);
        $b = StorageLocation::create(['name' => 'B', 'code' => 'B', 'type' => 'rack']);
        $this->binA = InventoryLocation::create(['product_id' => $this->product->id, 'storage_location_id' => $a->id, 'quantity' => 10, 'is_primary' => true]);
        $this->binB = InventoryLocation::create(['product_id' => $this->product->id, 'storage_location_id' => $b->id, 'quantity' => 5]);
    }

    private function onHand(?Product $p = null): float
    {
        return (float) \DB::table('products')->where('id', ($p ?? $this->product)->id)->value('quantity_on_hand');
    }

    private function ledger(?Product $p = null)
    {
        return InventoryTransaction::where('product_id', ($p ?? $this->product)->id)->orderBy('id');
    }

    public function test_bin_adjust_changes_the_total_and_refuses_to_go_negative(): void
    {
        $this->postJson("/api/v1/products/{$this->product->id}/locations/{$this->binA->id}/adjust", ['quantity' => -4, 'type' => 'adjustment'])->assertOk();
        $this->assertEquals(11, $this->onHand());

        $this->postJson("/api/v1/products/{$this->product->id}/locations/{$this->binA->id}/adjust", ['quantity' => -50, 'type' => 'adjustment'])->assertStatus(422);
        $this->assertEquals(11, $this->onHand());
        $this->assertSame(1, $this->ledger()->count());
    }

    public function test_adjusting_a_bin_requires_the_adjust_permission(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]), ['*']);

        $this->postJson("/api/v1/products/{$this->product->id}/locations/{$this->binA->id}/adjust", ['quantity' => 1, 'type' => 'receipt'])->assertForbidden();
        $this->assertEquals(15, $this->onHand());
    }

    public function test_transfer_between_bins_keeps_the_total(): void
    {
        $this->postJson("/api/v1/products/{$this->product->id}/locations/transfer", [
            'from_location_id' => $this->binA->id, 'to_location_id' => $this->binB->id, 'quantity' => 6,
        ])->assertOk();

        $this->assertEquals(4, (float) $this->binA->fresh()->quantity);
        $this->assertEquals(11, (float) $this->binB->fresh()->quantity);
        $this->assertEquals(15, $this->onHand());

        $this->postJson("/api/v1/products/{$this->product->id}/locations/transfer", [
            'from_location_id' => $this->binA->id, 'to_location_id' => $this->binB->id, 'quantity' => 99,
        ])->assertStatus(422);
        $this->assertEquals(15, $this->onHand());
    }

    public function test_product_adjust_endpoint_draws_from_the_bins_and_logs(): void
    {
        $this->postJson("/api/v1/products/{$this->product->id}/adjust", ['quantity' => -12, 'type' => 'adjustment', 'reference_number' => 'CNT-1'])->assertOk();

        // primary bin emptied first, remainder from the other bin
        $this->assertEquals([0.0, 3.0], [(float) $this->binA->fresh()->quantity, (float) $this->binB->fresh()->quantity]);
        $this->assertEquals(3, $this->onHand());
        $tx = $this->ledger()->first();
        $this->assertEquals([-12.0, 15.0, 3.0], [(float) $tx->quantity, (float) $tx->quantity_before, (float) $tx->quantity_after]);

        $this->postJson("/api/v1/products/{$this->product->id}/adjust", ['quantity' => 2.5, 'type' => 'receipt'])->assertOk();
        $this->assertEquals(5.5, $this->onHand());
    }

    public function test_manual_transaction_receipt_and_issue_move_the_bins(): void
    {
        $body = fn (string $type, int $qty) => [
            'type' => $type, 'transaction_date' => now()->toDateString(), 'reference_number' => 'M-'.$type,
            'products' => [['product_id' => $this->product->id, 'quantity' => $qty]],
        ];

        $this->postJson('/api/v1/transactions/manual', $body('receipt', 5))->assertSuccessful();
        $this->assertEquals(20, $this->onHand());

        $this->postJson('/api/v1/transactions/manual', $body('issue', 8))->assertSuccessful();
        $this->assertEquals(12, $this->onHand());
        $this->assertEquals(12, (float) InventoryLocation::where('product_id', $this->product->id)->sum('quantity'));
    }

    public function test_deleting_a_transaction_reverses_it_on_the_bins(): void
    {
        $this->postJson('/api/v1/transactions/manual', [
            'type' => 'receipt', 'transaction_date' => now()->toDateString(), 'reference_number' => 'M-1',
            'products' => [['product_id' => $this->product->id, 'quantity' => 5]],
        ])->assertSuccessful();
        $this->assertEquals(20, $this->onHand());

        $this->deleteJson('/api/v1/transactions/'.$this->ledger()->first()->id)->assertOk();

        $this->assertEquals(15, $this->onHand());
        $this->assertEquals(15, (float) InventoryLocation::where('product_id', $this->product->id)->sum('quantity'));
    }

    public function test_product_import_creates_bins_and_records_changes_as_adjustments(): void
    {
        $header = 'SKU,Description,Unit Cost,Unit Price,Quantity On Hand,Minimum Quantity,Unit of Measure';
        $csv = fn (array $rows) => UploadedFile::fake()->createWithContent('p.csv', implode("\n", array_merge([$header], $rows)));

        $this->post('/api/v1/import/products', ['file' => $csv(['NEW-1,New part,1,2,12,0,EA', 'INV-1,Inv test,1,2,9,0,EA'])], ['Accept' => 'application/json'])->assertSuccessful();

        $new = Product::where('sku', 'NEW-1')->first();
        $this->assertEquals(12, (float) InventoryLocation::where('product_id', $new->id)->sum('quantity'));
        $this->assertEquals(12, $this->onHand($new));
        $this->assertSame(0, $this->ledger($new)->count()); // opening stock is not a movement

        // existing product: sheet says 9, bins held 15 -> an adjustment of -6
        $this->assertEquals(9, $this->onHand());
        $tx = $this->ledger()->first();
        $this->assertEquals([-6.0, 'adjustment'], [(float) $tx->quantity, $tx->type]);
    }

    public function test_installing_a_consumable_tool_draws_one_from_the_bins(): void
    {
        $this->product->update(['tool_type' => 'consumable_tool']);
        $machine = Machine::create(['name' => 'Saw 1', 'equipment_type' => 'machine']);

        $this->postJson("/api/v1/machines/{$machine->id}/tooling", [
            'product_id' => $this->product->id, 'location_on_machine' => 'Spindle', 'installed_at' => now()->toDateString(),
        ])->assertSuccessful();

        $this->assertEquals(14, $this->onHand());
        $this->assertEquals(14, (float) InventoryLocation::where('product_id', $this->product->id)->sum('quantity'));
        $tx = $this->ledger()->first();
        $this->assertEquals([-1.0, 'issue'], [(float) $tx->quantity, $tx->type]);
    }
}
