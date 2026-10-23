<?php

namespace Tests\Feature;

use App\Models\JobReservation;
use App\Models\JobReservationItem;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A part that jobs have committed stock against, with nothing on hand, is a
 * real shortage: critical and suggested for ordering whatever its flags say.
 */
class CommittedZeroStockCriticalTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attrs = []): Product
    {
        static $n = 0;
        $n++;

        return Product::create(array_merge([
            'sku' => "CZ-{$n}",
            'description' => 'Part',
            'supplier_id' => Supplier::create(['name' => "S{$n}"])->id,
            'quantity_on_hand' => 0,
            'is_active' => true,
        ], $attrs));
    }

    private function commit(Product $p, float $qty, string $status = 'active'): void
    {
        static $r = 0;
        $r++;
        $res = JobReservation::create([
            'job_number' => "J-{$r}", 'release_number' => 1, 'reservation_id' => $r,
            'job_name' => 'Job', 'requested_by' => 'T', 'status' => $status,
        ]);
        JobReservationItem::create([
            'reservation_id' => $res->id, 'product_id' => $p->id,
            'requested_qty' => $qty, 'committed_qty' => $qty,
        ]);
    }

    public function test_special_order_part_with_commitments_and_nothing_on_hand_is_critical(): void
    {
        $p = $this->product(['is_special_order' => true]); // no reorder point
        $this->commit($p, 5);

        $p->refresh()->updateStatus();

        $this->assertSame('critical', $p->fresh()->status);
    }

    public function test_boneyard_part_is_critical_too(): void
    {
        $p = $this->product(['nonsof' => true]);
        $this->commit($p, 2);

        $p->refresh()->updateStatus();

        $this->assertSame('critical', $p->fresh()->status);
    }

    public function test_zero_stock_with_no_commitments_stays_out_of_stock(): void
    {
        $p = $this->product(['is_special_order' => true]);

        $p->updateStatus();

        $this->assertSame('out_of_stock', $p->fresh()->status);
    }

    public function test_cancelled_reservations_do_not_count(): void
    {
        $p = $this->product();
        $this->commit($p, 4, 'cancelled');

        $p->refresh()->updateStatus();

        $this->assertSame('out_of_stock', $p->fresh()->status);
    }

    public function test_suggested_order_qty_covers_the_commitment_without_a_reorder_point(): void
    {
        $p = $this->product(['is_special_order' => true]);
        $this->commit($p, 6);

        $this->assertSame(6, (int) $p->fresh()->suggested_order_qty);
    }

    public function test_suggested_qty_is_net_of_inbound_and_respects_order_rules(): void
    {
        $p = $this->product(['order_multiple' => 4, 'on_order_qty' => 2]);
        $this->commit($p, 7); // 7 committed - 2 inbound = 5 -> rounds up to a multiple of 4

        $this->assertSame(8, (int) $p->fresh()->suggested_order_qty);
    }

    public function test_parts_with_stock_on_hand_are_unaffected(): void
    {
        $p = $this->product(['quantity_on_hand' => 10]);
        $this->commit($p, 3);

        $this->assertSame(0, (int) $p->fresh()->suggested_order_qty);
    }
}
