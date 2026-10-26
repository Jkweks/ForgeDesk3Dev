<?php

namespace Tests\Feature;

use App\Models\InventoryLocation;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Shipping an order must take stock out of the bins (the source of truth) and derive the product
 * total from them. It used to edit quantity_on_hand directly, so the next recalculation from
 * the bins silently put the shipped units back.
 */
class OrderShipInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipping_draws_down_the_bins_and_survives_a_recalculation(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($user, ['*']);

        $product = Product::create([
            'sku' => 'SHIP-1',
            'description' => 'Ship test',
            'supplier_id' => Supplier::create(['name' => 'Ship Supplier'])->id,
        ]);
        $primary = StorageLocation::create(['name' => 'Rack A', 'code' => 'A', 'type' => 'rack']);
        $overflow = StorageLocation::create(['name' => 'Rack B', 'code' => 'B', 'type' => 'rack']);
        InventoryLocation::create(['product_id' => $product->id, 'storage_location_id' => $primary->id, 'quantity' => 6, 'is_primary' => true]);
        InventoryLocation::create(['product_id' => $product->id, 'storage_location_id' => $overflow->id, 'quantity' => 4, 'is_primary' => false]);
        $product->recalculateQuantitiesFromLocations();

        $order = Order::create([
            'order_number' => 'SHIP-ORD-1', 'customer_name' => 'Customer',
            'order_date' => now()->toDateString(), 'user_id' => $user->id,
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 8, 'unit_price' => 1, 'line_total' => 8]);

        $this->postJson("/api/v1/orders/{$order->id}/ship", ['actual_ship_date' => now()->toDateString()])->assertOk();

        // Primary bin first, then the overflow bin
        $this->assertEquals(0, InventoryLocation::where('storage_location_id', $primary->id)->value('quantity'));
        $this->assertEquals(2, InventoryLocation::where('storage_location_id', $overflow->id)->value('quantity'));
        $this->assertEquals(2, (float) $product->fresh()->quantity_on_hand);

        // Recalculating from the bins must not bring the shipped units back
        $this->assertEquals(2, (float) $product->fresh()->recalculateQuantitiesFromLocations()->quantity_on_hand);

        $tx = InventoryTransaction::where('product_id', $product->id)->where('type', 'shipment')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(-8, (float) $tx->quantity);
        $this->assertEquals($user->id, $tx->user_id);
        $this->assertEquals('shipped', $order->fresh()->status);
    }
}
