<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Project Management roles and job scope (docs/plans/pm-suite-integration.md, feature #14). */
class PmRolesTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): User
    {
        $u = User::factory()->create(['role' => $role, 'is_active' => true]);
        Sanctum::actingAs($u, ['*']);

        return $u;
    }

    private function bid(string $number, ?User $pm = null): int
    {
        return BusinessJob::withoutGlobalScopes()->create([
            'job_number' => $number, 'job_name' => "Job $number", 'status' => 'bidding', 'origin' => 'pm',
            'project_manager_id' => $pm?->id,
        ])->id;
    }

    public function test_roles_and_permissions_are_seeded(): void
    {
        $pm = Role::where('name', 'project_manager')->firstOrFail();
        $this->assertTrue($pm->hasPermission('pm.jobs.manage'));
        $this->assertFalse($pm->hasPermission('pm.jobs.view_all'));
        $this->assertTrue(Role::where('name', 'office_staff')->first()->hasPermission('pm.jobs.view_all'));
        $this->assertTrue(Role::where('name', 'manager')->first()->hasPermission('pm.act_for_others'));
        $this->assertTrue(Role::where('name', 'superintendent')->first()->hasPermission('pm.superintendent'));
    }

    public function test_project_manager_sees_only_own_jobs_but_manager_sees_all(): void
    {
        $sarah = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $mine = $this->bid('7001', $sarah);
        $theirs = $this->bid('7002', $other);

        Sanctum::actingAs($sarah, ['*']);
        $ids = collect($this->getJson('/api/v1/pm/jobs')->assertOk()->json())->pluck('id');
        $this->assertTrue($ids->contains($mine));
        $this->assertFalse($ids->contains($theirs));
        $this->getJson("/api/v1/pm/jobs/{$theirs}")->assertNotFound();
        $this->getJson("/api/v1/pm/jobs/{$theirs}/estimates")->assertNotFound();
        $this->getJson("/api/v1/pm/jobs/{$theirs}/forecast")->assertNotFound();
        $this->postJson("/api/v1/pm/jobs/{$theirs}/stage", ['stage' => 'won'])->assertNotFound();
        $this->getJson('/api/v1/dashboard/widgets/pm/jobs')->assertOk()->assertJsonPath('bidding', 1);

        $this->as('manager');
        $this->assertCount(2, $this->getJson('/api/v1/pm/jobs')->assertOk()->json());
    }

    public function test_job_purchase_orders_follow_the_same_scope(): void
    {
        $sarah = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $theirs = $this->bid('7003', User::factory()->create(['role' => 'project_manager']));
        $vendor = Supplier::create(['name' => 'V', 'is_active' => true]);
        $line = [['description' => 'x', 'quantity' => 1, 'unit_cost' => 5]];

        $this->as('manager');
        $poId = $this->postJson('/api/v1/job-purchase-orders', ['supplier_id' => $vendor->id, 'business_job_id' => $theirs,
            'order_date' => now()->toDateString(), 'lines' => $line])->assertCreated()->json('purchase_order.id');

        Sanctum::actingAs($sarah, ['*']);
        $this->getJson('/api/v1/job-purchase-orders')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/job-purchase-orders/{$poId}")->assertNotFound();
        $this->postJson('/api/v1/job-purchase-orders', ['supplier_id' => $vendor->id, 'business_job_id' => $theirs,
            'order_date' => now()->toDateString(), 'lines' => $line])->assertNotFound();
    }

    public function test_creating_jobs_defaults_to_yourself_and_acting_for_others_needs_permission(): void
    {
        $sarah = $this->as('project_manager');
        $someoneElse = User::factory()->create(['role' => 'project_manager']);

        $this->postJson('/api/v1/pm/jobs', ['job_number' => '7010', 'job_name' => 'Mine'])->assertCreated()->assertJsonPath('project_manager_id', $sarah->id);
        $this->postJson('/api/v1/pm/jobs', ['job_number' => '7011', 'job_name' => 'Theirs', 'project_manager_id' => $someoneElse->id])->assertForbidden();

        $this->as('manager');
        $this->postJson('/api/v1/pm/jobs', ['job_number' => '7012', 'job_name' => 'On behalf', 'project_manager_id' => $someoneElse->id])
            ->assertCreated()->assertJsonPath('project_manager_id', $someoneElse->id);
    }
}
