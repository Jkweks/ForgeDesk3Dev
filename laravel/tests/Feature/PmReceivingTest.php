<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Receiving overview (docs/plans/pm-suite-integration.md, feature #3). */
class PmReceivingTest extends TestCase
{
    use RefreshDatabase;

    public function test_outstanding_lists_only_issued_open_lines_and_flagged_lists_overreceipts(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $job = BusinessJob::withoutGlobalScopes()->create(['job_number' => '3001', 'job_name' => 'R', 'status' => 'won']);
        $vendor = Supplier::create(['name' => 'Glass Co', 'is_active' => true]);
        $make = fn (string $stage, array $over = []) => $this->postJson('/api/v1/job-purchase-orders', array_merge([
            'supplier_id' => $vendor->id, 'business_job_id' => $job->id, 'order_date' => now()->subWeek()->toDateString(), 'issue_stage' => $stage,
            'expected_date' => now()->subDay()->toDateString(),
            'lines' => [['description' => 'Lites', 'quantity' => 10, 'unit_cost' => 5], ['description' => 'Sealant', 'quantity' => 2, 'unit_cost' => 5]],
        ], $over))->assertCreated()->json('purchase_order');

        $issued = $make('material-release');
        $make('draft');                                   // drafts are not expected yet
        $voided = $make('material-release');
        $this->postJson("/api/v1/job-purchase-orders/{$voided['id']}/void", ['reason' => 'x'])->assertOk();

        $out = $this->getJson('/api/v1/pm/receiving/outstanding')->assertOk()->json();
        $this->assertCount(1, $out);
        $this->assertSame($issued['po_number'], $out[0]['po_number']);
        $this->assertTrue($out[0]['overdue']);
        $this->assertCount(2, $out[0]['lines']);

        // receive Sealant fully, Lites over the order -> flagged; PO then has nothing outstanding for Sealant
        $lites = $issued['lines'][0]['id'];
        $sealant = $issued['lines'][1]['id'];
        $this->postJson("/api/v1/job-purchase-orders/{$issued['id']}/receive", ['receipts' => [['item_id' => $sealant, 'quantity' => 2], ['item_id' => $lites, 'quantity' => 12]]])->assertOk();

        $this->assertCount(0, $this->getJson('/api/v1/pm/receiving/outstanding')->json());
        $flags = $this->getJson('/api/v1/pm/receiving/flagged')->assertOk()->json();
        $this->assertCount(1, $flags);
        $this->assertSame('Lites', $flags[0]['line']);
        $this->assertEquals(12, $flags[0]['quantity']);
    }

    public function test_scope_and_page(): void
    {
        $owner = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $job = BusinessJob::withoutGlobalScopes()->create(['job_number' => '3002', 'job_name' => 'S', 'status' => 'won', 'project_manager_id' => $owner->id]);
        $vendor = Supplier::create(['name' => 'V', 'is_active' => true]);

        Sanctum::actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]), ['*']);
        $this->postJson('/api/v1/job-purchase-orders', ['supplier_id' => $vendor->id, 'business_job_id' => $job->id, 'order_date' => now()->toDateString(),
            'issue_stage' => 'material-release', 'lines' => [['description' => 'x', 'quantity' => 1, 'unit_cost' => 1]]])->assertCreated();

        Sanctum::actingAs($owner, ['*']);
        $this->assertCount(1, $this->getJson('/api/v1/pm/receiving/outstanding')->json());
        Sanctum::actingAs(User::factory()->create(['role' => 'project_manager', 'is_active' => true]), ['*']);
        $this->assertCount(0, $this->getJson('/api/v1/pm/receiving/outstanding')->json());

        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->get('/pm/receiving')->assertOk()->assertSee('Still expected');
    }
}
