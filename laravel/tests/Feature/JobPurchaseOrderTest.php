<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Job-specific POs never touch stock (docs/plans/pm-suite-integration.md). */
class JobPurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $vendor;

    private BusinessJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->vendor = Supplier::create(['name' => 'Glass Co', 'is_active' => true]);
        $this->job = BusinessJob::create(['job_number' => '2001', 'job_name' => 'Job', 'status' => 'active']);
    }

    private function create(array $over = []): array
    {
        return $this->postJson('/api/v1/job-purchase-orders', array_merge([
            'supplier_id' => $this->vendor->id,
            'business_job_id' => $this->job->id,
            'order_date' => now()->toDateString(),
            'cost_code' => '3000',
            'lines' => [
                ['description' => 'Glass group', 'kind' => 'glass', 'children' => [
                    ['description' => 'Lite A', 'quantity' => 4, 'unit_cost' => 25],
                    ['description' => 'Lite B', 'quantity' => 2, 'unit_cost' => 50, 'cost_code' => '3010'],
                ]],
                ['description' => 'Sealant', 'quantity' => 10, 'unit_cost' => 3],
            ],
        ], $over))->assertCreated()->json('purchase_order');
    }

    public function test_creates_grouped_job_po_with_totals_and_no_stock_effect(): void
    {
        $po = $this->create();

        $this->assertSame('job', $po['type']);
        $this->assertSame('draft', $po['issue_stage']);
        $this->assertEquals(230, $po['total_amount']);
        $this->assertCount(2, $po['lines']);
        $this->assertCount(2, $po['lines'][0]['children']);
        $this->assertSame('3000', $po['lines'][0]['children'][0]['cost_code']); // falls back to the PO-level cost code
        $this->assertSame('3010', $po['lines'][0]['children'][1]['cost_code']); // line override wins
    }

    public function test_receiving_records_arrival_and_leaves_stock_alone(): void
    {
        $stock = Product::create(['sku' => 'S1', 'description' => 'Stock', 'supplier_id' => $this->vendor->id, 'quantity_on_hand' => 5, 'on_order_qty' => 0]);
        $po = $this->create();
        $leaf = $po['lines'][1]['id'];

        $this->postJson("/api/v1/job-purchase-orders/{$po['id']}/receive", ['receipts' => [['item_id' => $leaf, 'quantity' => 3]]])
            ->assertStatus(422); // still draft

        $this->postJson("/api/v1/job-purchase-orders/{$po['id']}/stage", ['issue_stage' => 'material-release'])->assertOk();
        $r = $this->postJson("/api/v1/job-purchase-orders/{$po['id']}/receive", ['receipts' => [['item_id' => $leaf, 'quantity' => 3]]])->assertOk();
        $this->assertSame('partially_received', $r->json('status'));
        $this->assertCount(1, $r->json('receipts'));

        // over-receipt is allowed but flagged
        $r = $this->postJson("/api/v1/job-purchase-orders/{$po['id']}/receive", ['receipts' => [['item_id' => $leaf, 'quantity' => 20]]])->assertOk();
        $this->assertTrue($r->json('receipts.1.flagged'));

        $this->assertSame(5, (int) $stock->fresh()->quantity_on_hand);
        $this->assertSame(0, (int) $stock->fresh()->on_order_qty);
        $this->assertDatabaseCount('inventory_locations', 0);

        // group parents are not receivable
        $this->postJson("/api/v1/job-purchase-orders/{$po['id']}/receive", ['receipts' => [['item_id' => $po['lines'][0]['id'], 'quantity' => 1]]])->assertStatus(422);
    }

    public function test_stock_endpoints_exclude_and_refuse_job_pos(): void
    {
        $po = $this->create();
        $this->postJson("/api/v1/job-purchase-orders/{$po['id']}/stage", ['issue_stage' => 'material-release'])->assertOk();

        $ids = collect($this->getJson('/api/v1/purchase-orders')->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($po['id']));
        $this->getJson('/api/v1/purchase-orders-statistics')->assertOk()->assertJsonPath('total_orders', 0);

        $this->postJson("/api/v1/purchase-orders/{$po['id']}/receive", ['items' => [['item_id' => $po['lines'][1]['id'], 'quantity' => 1]]])->assertStatus(422);
        $this->postJson("/api/v1/purchase-orders/{$po['id']}/items", ['product_id' => 1, 'quantity' => 1, 'unit_cost' => 1])->assertStatus(422);
    }

    public function test_revision_snapshot_void_and_close(): void
    {
        $po = $this->create();
        $rev = $this->postJson("/api/v1/job-purchase-orders/{$po['id']}/revise")->assertOk();
        $this->assertSame(1, $rev->json('revision_number'));
        $this->assertCount(1, $rev->json('revisions'));
        $this->assertSame(0, $rev->json('revisions.0.revision_number'));

        $this->postJson("/api/v1/job-purchase-orders/{$po['id']}/void", [])->assertStatus(422);
        $this->postJson("/api/v1/job-purchase-orders/{$po['id']}/close")->assertOk();
        $this->postJson("/api/v1/job-purchase-orders/{$po['id']}/revise")->assertStatus(422);
        $this->postJson("/api/v1/job-purchase-orders/{$po['id']}/reopen")->assertOk();
        $this->postJson("/api/v1/job-purchase-orders/{$po['id']}/void", ['reason' => 'Wrong job'])->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertSame(PurchaseOrder::TYPE_JOB, PurchaseOrder::find($po['id'])->type);
        $this->getJson('/api/v1/job-purchase-orders')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/job-purchase-orders?include_voided=1')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_internal_vendor_not_allowed_on_job_po(): void
    {
        $internal = Supplier::create(['name' => 'Shop', 'is_active' => true, 'is_internal' => true]);
        $this->postJson('/api/v1/job-purchase-orders', [
            'supplier_id' => $internal->id, 'business_job_id' => $this->job->id, 'order_date' => now()->toDateString(),
            'lines' => [['description' => 'x', 'quantity' => 1, 'unit_cost' => 1]],
        ])->assertStatus(422);
    }
}
