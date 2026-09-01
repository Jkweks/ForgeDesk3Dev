<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\FdUser;
use App\Models\FdWoElevation;
use App\Models\FdWoStage;
use App\Models\FdWorkOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 3 — due-date-driven auto priority (with manual pins) + the kiosk
 * "My Work" queue.
 */
class WorkOrderPriorityTest extends TestCase
{
    use RefreshDatabase;

    private BusinessJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->job = BusinessJob::create(['job_number' => 'J1', 'job_name' => 'J', 'status' => 'active']);
    }

    private function wo(array $attr = []): FdWorkOrder
    {
        static $rel = 0;
        return FdWorkOrder::create(array_merge([
            'business_job_id' => $this->job->id,
            'release_number'  => ++$rel,
        ], $attr));
    }

    // ── resequencePriorities() ──────────────────────────────────────────────

    public function test_ranks_by_due_date_then_issue_date_nulls_last(): void
    {
        $late  = $this->wo(['due_date' => '2026-10-01']);
        $none  = $this->wo(['due_date' => null, 'date_issued' => '2026-01-01']);
        $early = $this->wo(['due_date' => '2026-09-01']);
        $none2 = $this->wo(['due_date' => null, 'date_issued' => '2026-02-01']);

        FdWorkOrder::resequencePriorities();

        $this->assertSame(1, $early->fresh()->priority);
        $this->assertSame(2, $late->fresh()->priority);
        $this->assertSame(3, $none->fresh()->priority);   // earlier date_issued
        $this->assertSame(4, $none2->fresh()->priority);
    }

    public function test_locked_work_order_holds_its_slot(): void
    {
        $a = $this->wo(['due_date' => '2026-09-01']);
        $b = $this->wo(['due_date' => '2026-09-02']);
        $c = $this->wo(['due_date' => '2026-09-03', 'priority' => 1, 'priority_locked' => true]);

        FdWorkOrder::resequencePriorities();

        $this->assertSame(1, $c->fresh()->priority);       // pinned, keeps slot 1
        $this->assertSame(2, $a->fresh()->priority);
        $this->assertSame(3, $b->fresh()->priority);
    }

    public function test_colliding_locked_priorities_are_uniquified(): void
    {
        $a = $this->wo(['due_date' => '2026-09-05', 'priority' => 1, 'priority_locked' => true]);
        $b = $this->wo(['due_date' => '2026-09-06', 'priority' => 1, 'priority_locked' => true]);
        $c = $this->wo(['due_date' => '2026-09-01']);

        FdWorkOrder::resequencePriorities();

        $this->assertSame([1, 2, 3], collect([$a, $b, $c])->map(fn ($w) => $w->fresh()->priority)->sort()->values()->all());
        // the unlocked one lands in whatever slot is left
        $this->assertContains($c->fresh()->priority, [1, 2, 3]);
    }

    // ── controller wiring ──────────────────────────────────────────────────

    public function test_creating_a_work_order_resequences(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $existing = $this->wo(['due_date' => '2026-12-01']);
        FdWorkOrder::resequencePriorities();
        $this->assertSame(1, $existing->fresh()->priority);

        $newId = $this->postJson('/api/v1/work-orders', [
            'business_job_id' => $this->job->id,
            'due_date'        => '2026-06-01',
        ])->assertCreated()->json('id');

        // new (earlier due) WO takes slot 1, existing drops to 2
        $this->assertSame(2, $existing->fresh()->priority);
        $this->assertSame(1, FdWorkOrder::whereKey($newId)->value('priority'));
    }

    public function test_due_date_edit_resequences(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $a = $this->wo(['due_date' => '2026-09-01']);
        $b = $this->wo(['due_date' => '2026-09-02']);
        FdWorkOrder::resequencePriorities();

        $this->patchJson("/api/v1/work-orders/{$b->id}", ['due_date' => '2026-08-01'])->assertOk();

        $this->assertSame(1, $b->fresh()->priority);
        $this->assertSame(2, $a->fresh()->priority);
    }

    public function test_reorder_endpoint_pins_in_given_order(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $a = $this->wo(['due_date' => '2026-09-01']);
        $b = $this->wo(['due_date' => '2026-09-02']);
        $c = $this->wo(['due_date' => '2026-09-03']);

        $this->postJson('/api/v1/work-orders/reorder', ['ordered_ids' => [$c->id, $a->id, $b->id]])->assertOk();

        $this->assertSame([1, 2, 3], [$c->fresh()->priority, $a->fresh()->priority, $b->fresh()->priority]);
        $this->assertTrue($c->fresh()->priority_locked);

        // a later resequence must not disturb the pinned order
        FdWorkOrder::resequencePriorities();
        $this->assertSame([1, 2, 3], [$c->fresh()->priority, $a->fresh()->priority, $b->fresh()->priority]);
    }

    public function test_resequence_endpoint_can_clear_locks(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $a = $this->wo(['due_date' => '2026-09-09', 'priority' => 1, 'priority_locked' => true]);
        $b = $this->wo(['due_date' => '2026-09-01']);

        $this->postJson('/api/v1/work-orders/resequence-priority', ['clear_locks' => true])->assertOk();

        $this->assertFalse($a->fresh()->priority_locked);
        $this->assertSame(1, $b->fresh()->priority);  // earliest due wins now
        $this->assertSame(2, $a->fresh()->priority);
    }

    public function test_console_command_resequences(): void
    {
        $a = $this->wo(['due_date' => '2026-09-02']);
        $b = $this->wo(['due_date' => '2026-09-01']);

        $this->artisan('fd:resequence-priorities')->assertExitCode(0);

        $this->assertSame(1, $b->fresh()->priority);
        $this->assertSame(2, $a->fresh()->priority);
    }

    // ── kiosk My Work queue ────────────────────────────────────────────────

    public function test_my_queue_ranks_actionable_stages_and_hides_gated(): void
    {
        $me = FdUser::create(['name' => 'Me', 'role' => 'worker', 'active' => true]);

        // WO 1: priority 1, one elevation, stages: Prep (assigned to me), Fab (gated)
        $wo1 = $this->wo(['due_date' => '2026-09-01', 'priority' => 1, 'priority_locked' => true]);
        $e1 = FdWoElevation::create(['work_order_id' => $wo1->id, 'elevation_tag' => 'E1', 'date_requested' => '2026-09-01']);
        $prep = FdWoStage::create(['elevation_id' => $e1->id, 'name' => 'Prep', 'sort_order' => 1, 'blocks_next' => true, 'status' => 'pending', 'assigned_to_id' => $me->id]);
        $fab  = FdWoStage::create(['elevation_id' => $e1->id, 'name' => 'Fab', 'sort_order' => 2, 'blocks_next' => true, 'status' => 'pending', 'assigned_to_id' => $me->id]);

        // WO 2: priority 2, unassigned stage but I'm on the crew
        $wo2 = $this->wo(['due_date' => '2026-09-02', 'priority' => 2, 'priority_locked' => true]);
        $wo2->assignedUsers()->attach($me->id);
        $e2 = FdWoElevation::create(['work_order_id' => $wo2->id, 'elevation_tag' => 'E2', 'date_requested' => '2026-09-05']);
        $crewStage = FdWoStage::create(['elevation_id' => $e2->id, 'name' => 'Cut', 'sort_order' => 1, 'blocks_next' => true, 'status' => 'pending', 'assigned_to_id' => null]);

        // Noise: another operator's stage, a completed elevation, an archived WO
        $other = FdUser::create(['name' => 'Other', 'role' => 'worker', 'active' => true]);
        FdWoStage::create(['elevation_id' => $e1->id, 'name' => 'QA', 'sort_order' => 3, 'blocks_next' => true, 'status' => 'pending', 'assigned_to_id' => $other->id]);

        $res = $this->getJson("/api/v1/shop/my-queue?fab_user_id={$me->id}")->assertOk();
        $queueNames = collect($res->json('queue'))->pluck('name')->all();
        $this->assertContains('Prep', $queueNames);       // assigned, not gated
        $this->assertContains('Cut', $queueNames);        // unassigned + on crew
        $this->assertNotContains('Fab', $queueNames);     // gated by Prep
        $this->assertNotContains('QA', $queueNames);      // someone else's

        // WO priority orders the queue: WO1's Prep before WO2's Cut
        $this->assertSame('Prep', $res->json('queue.0.name'));
        $this->assertSame('Cut', $res->json('queue.1.name'));
    }

    public function test_my_queue_puts_in_progress_first(): void
    {
        $me = FdUser::create(['name' => 'Me', 'role' => 'worker', 'active' => true]);
        $woA = $this->wo(['due_date' => '2026-09-01', 'priority' => 1, 'priority_locked' => true]);
        $woB = $this->wo(['due_date' => '2026-09-02', 'priority' => 2, 'priority_locked' => true]);
        $eA = FdWoElevation::create(['work_order_id' => $woA->id, 'elevation_tag' => 'A']);
        $eB = FdWoElevation::create(['work_order_id' => $woB->id, 'elevation_tag' => 'B']);
        FdWoStage::create(['elevation_id' => $eA->id, 'name' => 'A1', 'sort_order' => 1, 'blocks_next' => true, 'status' => 'pending', 'assigned_to_id' => $me->id]);
        FdWoStage::create(['elevation_id' => $eB->id, 'name' => 'B1', 'sort_order' => 1, 'blocks_next' => true, 'status' => 'in_progress', 'assigned_to_id' => $me->id]);

        $queue = $this->getJson("/api/v1/shop/my-queue?fab_user_id={$me->id}")->assertOk()->json('queue');

        $this->assertSame('B1', $queue[0]['name']);   // in_progress floats up despite lower WO priority
        $this->assertSame('A1', $queue[1]['name']);
    }
}
