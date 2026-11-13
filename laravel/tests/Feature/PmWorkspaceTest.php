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

        $this->get('/pm')->assertOk()->assertSee('Project Management')->assertSee('workspace-switcher', false);
        $this->get('/pm/jobs')->assertOk();
        $this->get('/pm/vendors')->assertOk();
        $this->get('/')->assertOk()->assertSee('workspace-switcher', false);
    }
}
