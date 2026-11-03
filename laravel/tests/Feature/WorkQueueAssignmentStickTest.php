<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\FdUser;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Models\FdWoStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Manual Work Queue assignment must survive edits to the work order / elevation. */
class WorkQueueAssignmentStickTest extends TestCase
{
    use RefreshDatabase;

    private FdWorkOrder $wo;

    private FdWoElevation $elev;

    private FdUser $a;

    private FdUser $b;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $job = BusinessJob::create(['job_number' => 'J1', 'job_name' => 'Job', 'status' => 'active']);
        $this->wo = FdWorkOrder::create(['business_job_id' => $job->id, 'release_number' => 1]);
        $this->elev = FdWoElevation::create(['work_order_id' => $this->wo->id, 'elevation_tag' => 'E1']);
        $this->a = FdUser::create(['name' => 'Ann', 'role' => 'worker', 'active' => true]);
        $this->b = FdUser::create(['name' => 'Bo', 'role' => 'worker', 'active' => true]);
        // The work order's crew is A and B...
        $this->wo->assignedUsers()->sync([$this->a->id, $this->b->id]);
    }

    private function stage(string $name): FdWoStage
    {
        return FdWoStage::create(['elevation_id' => $this->elev->id, 'name' => $name, 'sort_order' => 1, 'status' => 'pending']);
    }

    public function test_unassigning_a_stage_clears_it(): void
    {
        $s = $this->stage('Member Fab');
        $s->syncAssignees([$this->a->id, $this->b->id]);

        $this->patchJson("/api/v1/work-order-stages/{$s->id}", ['assigned_to_id' => null, 'log_message' => 'Unassigned'])->assertOk();

        $this->assertSame([], $s->fresh()->assignees->pluck('id')->all());
        $this->assertNull($s->fresh()->assigned_to_id);
    }

    public function test_editing_work_order_info_leaves_stage_assignment_alone(): void
    {
        $s = $this->stage('Member Fab');
        $s->syncAssignees([]);

        $this->patchJson("/api/v1/work-orders/{$this->wo->id}", ['notes' => 'changed', 'due_date' => '2026-12-01', 'priority' => 3])->assertOk();
        $this->assertSame([], $s->fresh()->assignees->pluck('id')->all());
    }

    public function test_resaving_the_same_crew_does_not_reassign_stages(): void
    {
        $s = $this->stage('Member Fab');
        $s->syncAssignees([]);

        $this->putJson("/api/v1/work-orders/{$this->wo->id}/assignments", ['user_ids' => [$this->a->id, $this->b->id]])->assertOk();
        $this->assertSame([], $s->fresh()->assignees->pluck('id')->all());
    }

    public function test_editing_an_elevation_leaves_stage_assignment_alone(): void
    {
        $s = $this->stage('Member Fab');
        $s->syncAssignees([$this->a->id]);

        $this->patchJson("/api/v1/elevations/{$this->elev->id}", ['elevation_tag' => 'E1-renamed', 'notes' => 'x', 'quantity' => 2])->assertOk();
        $this->assertSame([$this->a->id], $s->fresh()->assignees->pluck('id')->all());
    }
}
