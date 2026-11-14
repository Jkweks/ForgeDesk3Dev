<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\PmChangeLog;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Change history (docs/plans/pm-suite-integration.md, feature #11). */
class PmChangeHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($this->admin, ['*']);
    }

    public function test_job_changes_are_logged_field_by_field_with_the_user(): void
    {
        $id = $this->postJson('/api/v1/pm/jobs', ['job_number' => '5001', 'job_name' => 'Hist'])->assertCreated()->json('id');
        $this->postJson("/api/v1/pm/jobs/{$id}/stage", ['stage' => 'won'])->assertOk();

        $logs = PmChangeLog::where('entity_type', 'job')->where('entity_id', $id)->orderBy('id')->get();
        $this->assertSame(['created', 'updated'], $logs->pluck('action')->all());
        $this->assertSame($this->admin->id, $logs[1]->user_id);
        $this->assertSame('5001 Hist', $logs[1]->entity_label);

        $field = $logs[1]->fields->firstWhere('field', 'status');
        $this->assertSame(['bidding', 'won'], [$field->old_value, $field->new_value]);
        $this->assertNull($logs[1]->fields->firstWhere('field', 'updated_at'));
    }

    public function test_only_job_pos_and_pm_records_are_logged_not_stock_pos(): void
    {
        $vendor = Supplier::create(['name' => 'Glass Co', 'is_active' => true]);
        $job = BusinessJob::withoutGlobalScopes()->create(['job_number' => '5002', 'job_name' => 'P', 'status' => 'won']);

        $this->postJson('/api/v1/job-purchase-orders', ['supplier_id' => $vendor->id, 'business_job_id' => $job->id,
            'order_date' => now()->toDateString(), 'lines' => [['description' => 'x', 'quantity' => 1, 'unit_cost' => 5]]])->assertCreated();
        $product = \App\Models\Product::create(['sku' => 'S', 'description' => 'S', 'supplier_id' => $vendor->id, 'quantity_on_hand' => 0, 'on_order_qty' => 0]);
        $this->postJson('/api/v1/purchase-orders', ['supplier_id' => $vendor->id, 'order_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 1]]])->assertCreated();

        $this->assertSame(1, PmChangeLog::where('entity_type', 'po')->where('action', 'created')->count()); // the job PO only
        $this->assertSame($job->id, PmChangeLog::where('entity_type', 'po')->first()->business_job_id);
        $this->assertTrue(PmChangeLog::where('entity_type', 'vendor')->exists());
    }

    public function test_history_api_filters_and_follows_job_scope(): void
    {
        $mine = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $theirs = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);

        Sanctum::actingAs($mine, ['*']);
        $a = $this->postJson('/api/v1/pm/jobs', ['job_number' => '5003', 'job_name' => 'Mine'])->json('id');
        Sanctum::actingAs($theirs, ['*']);
        $this->postJson('/api/v1/pm/jobs', ['job_number' => '5004', 'job_name' => 'Theirs'])->assertCreated();

        Sanctum::actingAs($mine, ['*']);
        $labels = collect($this->getJson('/api/v1/pm/history?entity_type=job')->assertOk()->json('data'))->pluck('entity_label');
        $this->assertTrue($labels->contains('5003 Mine'));
        $this->assertFalse($labels->contains('5004 Theirs'));
        $this->assertCount(1, $this->getJson("/api/v1/pm/history?entity_type=job&entity_id={$a}&action=created")->json('data'));
        $this->assertCount(0, $this->getJson('/api/v1/pm/history?search=nomatch')->json('data'));

        Sanctum::actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]), ['*']);
        $all = collect($this->getJson('/api/v1/pm/history?entity_type=job')->json('data'))->pluck('entity_label');
        $this->assertTrue($all->contains('5004 Theirs'));
    }

    public function test_page_renders_and_needs_the_history_permission(): void
    {
        $this->actingAs($this->admin);
        $this->get('/pm/history')->assertOk()->assertSee('Change History');

        \App\Models\Role::firstOrCreate(['name' => 'shopper'], ['display_name' => 'Shopper']);
        Sanctum::actingAs(User::factory()->create(['role' => 'shopper', 'is_active' => true]), ['*']);
        $this->getJson('/api/v1/pm/history')->assertForbidden();
    }
}
