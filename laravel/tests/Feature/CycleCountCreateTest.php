<?php

namespace Tests\Feature;

use App\Models\CycleCountSession;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Stock quantities are numeric(12,1) in Postgres, so a location's quantity arrives as a
 * string like "14.0" while cycle_count_items.system_quantity is an integer column. Creating a
 * count used to 500 on Postgres ("invalid input syntax for type integer: \"14.0\"").
 */
class CycleCountCreateTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $sku, float $qty, array $extra = []): Product
    {
        $p = Product::create(['sku' => $sku, 'description' => $sku, 'supplier_id' => Supplier::firstOrCreate(['name' => 'CC Supplier'])->id] + $extra);
        $loc = StorageLocation::firstOrCreate(['name' => 'Bin A'], ['code' => 'A']);
        InventoryLocation::create(['product_id' => $p->id, 'storage_location_id' => $loc->id, 'quantity' => $qty]);

        return $p;
    }

    public function test_session_is_created_with_whole_number_expected_quantities(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $a = $this->product('CC-A', 14.0);
        $b = $this->product('CC-B', 14.6);

        $res = $this->postJson('/api/v1/cycle-counts', ['scheduled_date' => now()->toDateString(), 'product_ids' => [$a->id, $b->id]])->assertCreated();

        $qty = collect($res->json('session.items'))->pluck('system_quantity', 'product_id');
        $this->assertSame(14, $qty[$a->id]);
        $this->assertSame(15, $qty[$b->id]);
    }

    public function test_session_numbers_never_reuse_a_soft_deleted_one(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $p = $this->product('CC-C', 3);
        $first = $this->postJson('/api/v1/cycle-counts', ['scheduled_date' => now()->toDateString(), 'product_ids' => [$p->id]])->assertCreated()->json('session.session_number');
        CycleCountSession::where('session_number', $first)->first()->delete();

        $second = $this->postJson('/api/v1/cycle-counts', ['scheduled_date' => now()->toDateString(), 'product_ids' => [$p->id]])->assertCreated()->json('session.session_number');
        $this->assertNotSame($first, $second);
    }
}
