<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Project Management to-dos (docs/plans/pm-suite-integration.md, feature #28). */
class PmTodoTest extends TestCase
{
    use RefreshDatabase;

    private function pm(): User
    {
        $u = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        Sanctum::actingAs($u, ['*']);

        return $u;
    }

    public function test_create_complete_reopen_and_default_assignee(): void
    {
        $me = $this->pm();
        $t = $this->postJson('/api/v1/pm/todos', ['title' => 'Order glass', 'due_date' => now()->subDay()->toDateString()])->assertCreated()->json();
        $this->assertSame($me->id, $t['assigned_to']);

        $this->getJson('/api/v1/dashboard/widgets/pm/todos')->assertOk()->assertJsonPath('mine_open', 1)->assertJsonPath('mine_overdue', 1);

        $this->postJson("/api/v1/pm/todos/{$t['id']}/complete")->assertOk()->assertJsonPath('completed_by', $me->id);
        $this->assertCount(0, $this->getJson('/api/v1/pm/todos')->json());
        $this->assertCount(1, $this->getJson('/api/v1/pm/todos?done=1')->json());
        $this->getJson('/api/v1/dashboard/widgets/pm/todos')->assertJsonPath('mine_open', 0);

        $this->postJson("/api/v1/pm/todos/{$t['id']}/reopen")->assertOk()->assertJsonPath('completed_at', null);
        $this->putJson("/api/v1/pm/todos/{$t['id']}", ['title' => 'Order glass today'])->assertOk()->assertJsonPath('title', 'Order glass today');
        $this->deleteJson("/api/v1/pm/todos/{$t['id']}")->assertOk();
    }

    public function test_visibility_follows_assignment_and_job_scope(): void
    {
        $sarah = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $job = BusinessJob::withoutGlobalScopes()->create(['job_number' => '4001', 'job_name' => 'T', 'status' => 'won', 'project_manager_id' => $sarah->id]);

        Sanctum::actingAs($sarah, ['*']);
        $onJob = $this->postJson('/api/v1/pm/todos', ['title' => 'On Sarah job', 'business_job_id' => $job->id])->assertCreated()->json('id');

        Sanctum::actingAs($other, ['*']);
        $this->getJson('/api/v1/pm/todos')->assertOk()->assertJsonCount(0);
        $this->postJson("/api/v1/pm/todos/{$onJob}/complete")->assertNotFound();
        $this->postJson('/api/v1/pm/todos', ['title' => 'x', 'business_job_id' => $job->id])->assertNotFound();
        $this->postJson('/api/v1/pm/todos', ['title' => 'for Sarah', 'assigned_to' => $sarah->id])->assertForbidden();

        // a manager (act_for_others + company scope) sees it and can assign to others
        Sanctum::actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]), ['*']);
        $this->assertCount(1, $this->getJson('/api/v1/pm/todos')->json());
        $this->postJson('/api/v1/pm/todos', ['title' => 'Assigned', 'assigned_to' => $other->id])->assertCreated();

        Sanctum::actingAs($other, ['*']);
        $this->assertCount(1, $this->getJson('/api/v1/pm/todos?mine=1')->json());
    }

    public function test_page_renders(): void
    {
        $this->actingAs($this->pm());
        $this->get('/pm/todos')->assertOk()->assertSee('To-do');
    }
}
