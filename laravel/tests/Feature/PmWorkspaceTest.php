<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Project Management workspace and job visibility (docs/plans/pm-suite-integration.md, 4d). */
class PmWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($u, ['*']);

        return $u;
    }

    public function test_bid_lifecycle_and_fabrication_visibility(): void
    {
        $this->admin();

        $id = $this->postJson('/api/v1/pm/jobs', ['job_number' => '9001', 'job_name' => 'Bid Job'])
            ->assertCreated()->assertJsonPath('status', 'bidding')->json('id');

        // Hidden from fabrication-side queries while bidding and after winning
        $this->assertSame(0, BusinessJob::where('job_number', '9001')->count());
        $this->postJson("/api/v1/pm/jobs/{$id}/stage", ['stage' => 'won'])->assertOk();
        $this->assertSame(0, BusinessJob::where('job_number', '9001')->count());
        $this->getJson("/api/v1/business-jobs/{$id}")->assertNotFound();

        // PM still sees it in every stage
        $this->getJson('/api/v1/pm/jobs?status=won')->assertOk()->assertJsonCount(1);

        // Activating hands it to fabrication
        $this->postJson("/api/v1/pm/jobs/{$id}/stage", ['stage' => 'active'])->assertOk();
        $this->assertSame(1, BusinessJob::where('job_number', '9001')->count());
    }

    public function test_fabrication_keeps_creating_jobs_and_pm_sees_them(): void
    {
        $this->admin();

        // Existing Fabrication flow is unchanged: a job created there is active and marked as such
        $id = $this->postJson('/api/v1/business-jobs', ['job_number' => '9100', 'job_name' => 'Shop Job'])->assertCreated()->json('job.id')
            ?? BusinessJob::where('job_number', '9100')->value('id');
        $job = BusinessJob::findOrFail($id);
        $this->assertSame('active', $job->status);
        $this->assertSame('fabrication', $job->origin);

        // ...and shows up in Project Management alongside PM bids
        $pmId = $this->postJson('/api/v1/pm/jobs', ['job_number' => '9101', 'job_name' => 'Bid'])->assertCreated()->assertJsonPath('origin', 'pm')->json('id');
        $rows = collect($this->getJson('/api/v1/pm/jobs')->assertOk()->json())->keyBy('id');
        $this->assertSame('fabrication', $rows[$id]['origin']);
        $this->assertSame('pm', $rows[$pmId]['origin']);

        // A PM bid reserves its job number against Fabrication creating a duplicate
        $this->postJson('/api/v1/business-jobs', ['job_number' => '9101', 'job_name' => 'Dup'])->assertStatus(422);
    }

    public function test_invalid_stage_moves_are_rejected(): void
    {
        $this->admin();
        $id = $this->postJson('/api/v1/pm/jobs', ['job_number' => '9002', 'job_name' => 'X'])->json('id');

        $this->postJson("/api/v1/pm/jobs/{$id}/stage", ['stage' => 'active'])->assertStatus(422); // must win first
        $this->postJson("/api/v1/pm/jobs/{$id}/stage", ['stage' => 'lost'])->assertOk();
        $this->postJson("/api/v1/pm/jobs/{$id}/stage", ['stage' => 'won'])->assertStatus(422);
        $this->postJson("/api/v1/pm/jobs/{$id}/stage", ['stage' => 'bidding'])->assertOk();
    }

    public function test_pm_jobs_api_requires_pm_permission(): void
    {
        $role = Role::firstOrCreate(['name' => 'shopper'], ['display_name' => 'Shopper']);
        Sanctum::actingAs(User::factory()->create(['role' => 'shopper', 'is_active' => true]), ['*']);

        $this->getJson('/api/v1/pm/jobs')->assertForbidden();
        $this->postJson('/api/v1/pm/jobs', ['job_number' => '1', 'job_name' => 'x'])->assertForbidden();
    }

    public function test_pm_status_survives_automatic_status_sync(): void
    {
        $this->admin();
        $id = $this->postJson('/api/v1/pm/jobs', ['job_number' => '9003', 'job_name' => 'X'])->json('id');
        $job = BusinessJob::withoutGlobalScopes()->findOrFail($id);
        $job->syncAutoStatus();
        $this->assertSame('bidding', $job->fresh()->status);
    }

    public function test_workspace_pages_and_switcher(): void
    {
        $this->actingAs($this->admin());

        $this->get('/pm')->assertOk()->assertSee('workspace-switcher', false);
        $this->get('/pm/jobs')->assertOk();
        $this->get('/pm/vendors')->assertOk();
        $this->get('/')->assertOk()->assertSee('workspace-switcher', false);
    }

    public function test_dashboard_is_shared_across_workspaces(): void
    {
        $user = $this->admin();
        $this->actingAs($user);

        // Same view on both URLs
        $a = $this->get('/')->assertOk()->getContent();
        $b = $this->get('/pm')->assertOk()->getContent();
        $this->assertStringContainsString('dashboard', strtolower($b));
        $this->assertSame(substr_count($a, 'dashboard-grid'), substr_count($b, 'dashboard-grid'));

        // One layout per user; PM widgets are in the catalog next to fabrication ones
        $keys = collect($this->getJson('/api/v1/dashboard/widgets')->assertOk()->json('widgets'))->pluck('key');
        $this->assertTrue($keys->contains('pm_bidding'));
        $this->assertTrue($keys->contains('wo_open'));

        // PM widget data counts bids that Fabrication cannot see
        $this->postJson('/api/v1/pm/jobs', ['job_number' => '9200', 'job_name' => 'Bid'])->assertCreated();
        $this->getJson('/api/v1/dashboard/widgets/pm/jobs')->assertOk()->assertJsonPath('bidding', 1);
        $this->getJson('/api/v1/dashboard/widgets/pm/jobs/list')->assertOk()->assertJsonPath('items.0.label', '9200');
        $this->getJson('/api/v1/dashboard/widgets/jobs')->assertOk()->assertJsonPath('active', 0);
    }

    public function test_job_po_pages_render_and_vendor_lookup_hides_internal_vendors(): void
    {
        $this->actingAs($this->admin());
        \App\Models\Supplier::create(['name' => 'Glass Co', 'is_active' => true]);
        \App\Models\Supplier::create(['name' => 'Shop', 'is_active' => true, 'is_internal' => true]);

        $this->get('/pm/purchase-orders')->assertOk()->assertSee('Job Purchase Orders');
        $this->get('/pm/purchase-orders/new')->assertOk()->assertSee('New purchase order');
        $this->get('/pm/purchase-orders/12')->assertOk();
        $this->get('/pm/purchase-orders/abc')->assertNotFound();

        $names = collect($this->getJson('/api/v1/pm/vendors')->assertOk()->json())->pluck('name');
        $this->assertTrue($names->contains('Glass Co'));
        $this->assertFalse($names->contains('Shop'));
        $this->assertFalse($names->contains('Vos Glass - Fabrication'));
    }
}
