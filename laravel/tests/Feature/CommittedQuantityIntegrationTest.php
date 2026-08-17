<?php

namespace Tests\Feature;

use App\Models\CommittedInventory;
use App\Models\JobReservation;
use App\Models\JobReservationItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the quantity_committed clobber bug found while
 * auditing inventory accounting: JobReservationItem's save/delete hook used to
 * unconditionally overwrite product.quantity_committed with only the
 * job-reservation total, silently erasing any sales-order commitment
 * (CommittedInventory) on the same product. Product::recalculateCommittedQuantity()
 * now sums both sources so neither system can clobber the other's commitment.
 */
class CommittedQuantityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_reservation_and_order_commitments_are_additive_not_clobbering(): void
    {
        $product = Product::create([
            'sku' => 'TEST-001',
            'description' => 'Test product',
        ]);

        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $reservation = JobReservation::create([
            'job_number' => 'JOB-1',
            'release_number' => 1,
            'reservation_id' => 1,
            'job_name' => 'Test Job',
            'requested_by' => 'Tester',
            'status' => 'active',
        ]);

        $reservationItem = JobReservationItem::create([
            'reservation_id' => $reservation->id,
            'product_id' => $product->id,
            'requested_qty' => 5,
            'committed_qty' => 5,
        ]);

        $product->refresh();
        $this->assertEquals(5.0, (float) $product->quantity_committed);

        $order = Order::create([
            'order_number' => 'ORD-1',
            'customer_name' => 'Test Customer',
            'order_date' => now()->toDateString(),
            'user_id' => $user->id,
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 10,
            'line_total' => 30,
        ]);

        CommittedInventory::create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'quantity_committed' => 3,
            'committed_date' => now()->toDateString(),
        ]);
        $product->recalculateCommittedQuantity();

        $product->refresh();
        $this->assertEquals(8.0, (float) $product->quantity_committed, 'Job (5) + order (3) commitments should both be reflected');

        // Before the fix, saving a JobReservationItem overwrote quantity_committed
        // with ONLY the job-reservation total, silently erasing the order's 3.
        $reservationItem->update(['committed_qty' => 7]);

        $product->refresh();
        $this->assertEquals(10.0, (float) $product->quantity_committed, 'Order commitment (3) must survive a job-reservation-triggered recalculation');

        // And releasing the order commitment must not clobber the job's share either.
        CommittedInventory::where('order_id', $order->id)->delete();
        $product->recalculateCommittedQuantity();

        $product->refresh();
        $this->assertEquals(7.0, (float) $product->quantity_committed, 'Job commitment (7) must survive after the order commitment is released');
    }

    public function test_order_commit_and_release_endpoints_update_committed_quantity(): void
    {
        $product = Product::create([
            'sku' => 'TEST-002',
            'description' => 'Test product 2',
            'quantity_on_hand' => 20,
        ]);

        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $order = Order::create([
            'order_number' => 'ORD-2',
            'customer_name' => 'Test Customer',
            'order_date' => now()->toDateString(),
            'user_id' => $user->id,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'unit_price' => 10,
            'line_total' => 40,
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($user, ['*']);

        $response = $this->postJson("/api/v1/orders/{$order->id}/commit");
        $response->assertOk();

        $product->refresh();
        $this->assertEquals(4.0, (float) $product->quantity_committed);

        $response = $this->postJson("/api/v1/orders/{$order->id}/release");
        $response->assertOk();

        $product->refresh();
        $this->assertEquals(0.0, (float) $product->quantity_committed);
    }
}
