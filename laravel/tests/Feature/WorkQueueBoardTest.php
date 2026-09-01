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
 * Phase 4 — the manager assignment board (GET /api/v1/work-queue).
 */
class WorkQueueBoardTest extends TestCase
{
    use RefreshDatabase;

    private BusinessJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->job = BusinessJob::create(['job_number' => 'J1', 'job_name' => 'J', 'status' => 'active']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
    }

    private function wo(array $attr = []): FdWorkOrder
    {
        static $rel = 0;
        return FdWorkOrder::create(array_merge([
            'business_job_id' => $this->job->id,
            'release_number'  => ++$rel,
        ], $attr));
    }

    private function elevation(FdWorkOrder $wo, ?string $requested = null): FdWoElevation
    {
        return FdWoElevation::create([
            'work_order_id'  => $wo->id,
            'elevation_tag'  => 'E' . $wo->id,
            'date_requested' => $requested,
        ]);
    }

    private function stage(FdWoElevation $e, string $name, int $order, array $attr = []): FdWoStage
    {
        return FdWoStage::create(array_merge([
            'elevation_id' => $e->id,
            'name'         => $name,
            'sort_order'   => $order,
            'blocks_next'  => true,
            'status'       => 'pending',
        ], $attr));
    }

    public function test_requires_fabrication_view_permission(): void
    {
        // A role with no fabrication permissions is refused (viewer legitimately
        // has fabrication.work-orders.view, so it is NOT the negative case).
        Sanctum::actingAs(User::factory()->create(['role' => 'no-perms-role', 'is_active' => true]), ['*']);
        $this->getJson('/api/v1/work-queue')->assertStatus(403);
    }

    public function test_groups_by_operator_and_marks_gated_stages(): void
    {
        $alice = FdUser::create(['name' => 'Alice', 'initials' => 'AL', 'role' => 'worker', 'active' => true]);
        $bob   = FdUser::create(['name' => 'Bob', 'initials' => 'BO', 'role' => 'worker', 'active' => true]);

        $wo = $this->wo(['priority' => 1, 'priority_locked' => true]);
        $e  = $this->elevation($wo, '2026-09-01');
        $this->stage($e, 'Prep', 1, ['assigned_to_id' => $alice->id]);   // Alice, startable
        $this->stage($e, 'Fab', 2, ['assigned_to_id' => $bob->id]);      // Bob, GATED by Prep — still shown

        $wo2 = $this->wo(['priority' => 2, 'priority_locked' => true]);
        $e2  = $this->elevation($wo2, '2026-09-10');
        $this->stage($e2, 'Cut', 1, ['assigned_to_id' => null]);         // unassigned, startable

        $res = $this->getJson('/api/v1/work-queue')->assertOk();
        $ops = collect($res->json('operators'))->keyBy('user.id');

        // Alice: one ready stage
        $this->assertSame(['Prep'], collect($ops[$alice->id]['stages'])->pluck('name')->all());
        $this->assertFalse($ops[$alice->id]['stages'][0]['gated']);
        $this->assertSame(1, $ops[$alice->id]['ready_count']);

        // Bob: keeps his gated stage, flagged with the blocker's name
        $this->assertTrue($ops->has($bob->id), 'gated stages are still shown for planning');
        $bobStage = $ops[$bob->id]['stages'][0];
        $this->assertTrue($bobStage['gated']);
        $this->assertSame('Prep', $bobStage['blocking_stage_name']);
        $this->assertSame(1, $ops[$bob->id]['count']);
        $this->assertSame(0, $ops[$bob->id]['ready_count']);

        $this->assertSame(['Cut'], collect($res->json('unassigned.stages'))->pluck('name')->all());
    }

    public function test_filters_by_work_order_and_by_job(): void
    {
        $al = FdUser::create(['name' => 'Al', 'role' => 'worker', 'active' => true]);

        $job2 = BusinessJob::create(['job_number' => 'J2', 'job_name' => 'Other', 'status' => 'active']);

        $woA = $this->wo(['priority' => 1, 'priority_locked' => true]);                       // job 1
        $woB = $this->wo(['priority' => 2, 'priority_locked' => true, 'business_job_id' => $job2->id]);
        $this->stage($this->elevation($woA), 'A-Weld', 1, ['assigned_to_id' => $al->id]);
        $this->stage($this->elevation($woB), 'B-Weld', 1, ['assigned_to_id' => $al->id]);

        $all = collect($this->getJson('/api/v1/work-queue')->json('operators'))
            ->firstWhere('user.id', $al->id)['stages'];
        $this->assertEqualsCanonicalizing(['A-Weld', 'B-Weld'], collect($all)->pluck('name')->all());

        $byWo = collect($this->getJson("/api/v1/work-queue?work_order_id={$woA->id}")->json('operators'))
            ->firstWhere('user.id', $al->id)['stages'];
        $this->assertSame(['A-Weld'], collect($byWo)->pluck('name')->all());

        $byJob = collect($this->getJson("/api/v1/work-queue?job_id={$job2->id}")->json('operators'))
            ->firstWhere('user.id', $al->id)['stages'];
        $this->assertSame(['B-Weld'], collect($byJob)->pluck('name')->all());
    }

    public function test_stage_rows_carry_wo_context_and_group_meta(): void
    {
        $al = FdUser::create(['name' => 'Al', 'role' => 'worker', 'active' => true]);
        $wo = $this->wo(['priority' => 3, 'due_date' => '2026-12-24', 'priority_locked' => true]);
        $e  = $this->elevation($wo, '2026-08-01');
        $this->stage($e, 'Weld', 1, ['assigned_to_id' => $al->id]);

        $op = collect($this->getJson('/api/v1/work-queue')->json('operators'))->firstWhere('user.id', $al->id);

        $this->assertSame('2026-08-01', $op['oldest_date_requested']);
        $card = $op['stages'][0];
        $this->assertSame('J1-R' . $wo->release_number, $card['release_label']);
        $this->assertSame(3, $card['priority']);
        $this->assertSame('2026-12-24', $card['due_date']);
        $this->assertSame($wo->id, $card['work_order_id']);
    }

    public function test_fab_users_list_lets_the_board_show_empty_columns(): void
    {
        FdUser::create(['name' => 'Empty Ed', 'role' => 'worker', 'active' => true]);
        $res = $this->getJson('/api/v1/work-queue')->assertOk();
        $this->assertContains('Empty Ed', collect($res->json('fab_users'))->pluck('name')->all());
    }

    public function test_drop_reassignment_via_stage_patch_writes_assignee_and_log(): void
    {
        $al = FdUser::create(['name' => 'Al', 'role' => 'worker', 'active' => true]);
        $wo = $this->wo(['priority' => 1, 'priority_locked' => true]);
        $e  = $this->elevation($wo);
        $s  = $this->stage($e, 'Weld', 1, ['assigned_to_id' => null]);

        $this->patchJson("/api/v1/work-order-stages/{$s->id}", [
            'assigned_to_id' => $al->id,
            'log_message'    => 'Reassigned via Work Queue',
        ])->assertOk();

        $this->assertSame($al->id, $s->fresh()->assigned_to_id);
        $this->assertDatabaseHas('fd_stage_log', ['stage_id' => $s->id, 'message' => 'Reassigned via Work Queue']);
    }

    public function test_ordering_in_progress_first_then_wo_priority(): void
    {
        $al = FdUser::create(['name' => 'Al', 'role' => 'worker', 'active' => true]);

        $hi = $this->wo(['priority' => 1, 'priority_locked' => true]);
        $lo = $this->wo(['priority' => 5, 'priority_locked' => true]);
        $eHi = $this->elevation($hi);
        $eLo = $this->elevation($lo);
        $this->stage($eHi, 'HiPending', 1, ['assigned_to_id' => $al->id]);
        $this->stage($eLo, 'LoActive', 1, ['assigned_to_id' => $al->id, 'status' => 'in_progress']);

        $names = collect($this->getJson('/api/v1/work-queue')->json('operators'))
            ->firstWhere('user.id', $al->id)['stages'];

        $this->assertSame('LoActive', $names[0]['name']);   // in_progress wins over WO priority
        $this->assertSame('HiPending', $names[1]['name']);
    }

    // ── Bulk assign (POST /work-order-stages/bulk-assign) ───────────────

    public function test_bulk_assign_sets_all_stages_of_a_name_across_a_work_orders_elevations(): void
    {
        Sanctum::actingAs(User::factory()->create(['name' => 'Manager Mo', 'role' => 'admin', 'is_active' => true]), ['*']);

        $al  = FdUser::create(['name' => 'Al', 'role' => 'worker', 'active' => true]);
        $bob = FdUser::create(['name' => 'Bob', 'role' => 'worker', 'active' => true]);

        $wo = $this->wo();
        $e1 = $this->elevation($wo);
        $e2 = $this->elevation($wo);
        $cut1 = $this->stage($e1, 'Cutting', 1, ['assigned_to_id' => $bob->id]);
        $cut2 = $this->stage($e2, 'Cutting', 1);
        $prog1 = $this->stage($e1, 'Programming', 2);

        $res = $this->postJson('/api/v1/work-order-stages/bulk-assign', [
            'work_order_id' => $wo->id,
            'assignments'   => [
                ['stage_name' => 'Cutting', 'assigned_to_id' => $al->id],
            ],
        ])->assertOk();

        $this->assertSame(2, $res->json('updated'));
        $this->assertSame(['Cutting' => 2], $res->json('by_stage'));
        $this->assertSame($al->id, $cut1->fresh()->assigned_to_id);
        $this->assertSame($al->id, $cut2->fresh()->assigned_to_id);
        $this->assertNull($prog1->fresh()->assigned_to_id); // untouched

        $this->assertDatabaseHas('fd_stage_log', [
            'stage_id' => $cut1->id,
            'message'  => 'Reassigned from Bob to Al via bulk assign by Manager Mo (bulk assign)',
        ]);
    }

    public function test_bulk_assign_null_assignee_unassigns(): void
    {
        $al = FdUser::create(['name' => 'Al', 'role' => 'worker', 'active' => true]);
        $wo = $this->wo();
        $s  = $this->stage($this->elevation($wo), 'Weld', 1, ['assigned_to_id' => $al->id]);

        $this->postJson('/api/v1/work-order-stages/bulk-assign', [
            'work_order_id' => $wo->id,
            'assignments'   => [['stage_name' => 'Weld', 'assigned_to_id' => null]],
        ])->assertOk()->assertJson(['updated' => 1]);

        $this->assertNull($s->fresh()->assigned_to_id);
    }

    public function test_bulk_assign_skips_terminal_stages_and_no_op_reassignments(): void
    {
        $al = FdUser::create(['name' => 'Al', 'role' => 'worker', 'active' => true]);
        $wo = $this->wo();
        $e  = $this->elevation($wo);
        $done = $this->stage($e, 'Cutting', 1, ['status' => 'complete', 'assigned_to_id' => null]);
        $already = $this->stage($e, 'Cutting', 2, ['assigned_to_id' => $al->id]);

        $res = $this->postJson('/api/v1/work-order-stages/bulk-assign', [
            'work_order_id' => $wo->id,
            'assignments'   => [['stage_name' => 'cutting', 'assigned_to_id' => $al->id]], // case-insensitive match
        ])->assertOk();

        $this->assertSame(0, $res->json('updated')); // completed skipped, already-assigned is a no-op
        $this->assertSame('complete', $done->fresh()->status);
        $this->assertNull($done->fresh()->assigned_to_id);
    }

    public function test_bulk_assign_supports_multiple_stage_names_and_job_scope(): void
    {
        $al  = FdUser::create(['name' => 'Al', 'role' => 'worker', 'active' => true]);
        $bob = FdUser::create(['name' => 'Bob', 'role' => 'worker', 'active' => true]);

        $job2 = BusinessJob::create(['job_number' => 'J2', 'job_name' => 'Other', 'status' => 'active']);
        $woA  = $this->wo();
        $woB  = $this->wo(['business_job_id' => $job2->id]);

        $cutA = $this->stage($this->elevation($woA), 'Cutting', 1);
        $progA = $this->stage($this->elevation($woA), 'Programming', 1);
        $cutB = $this->stage($this->elevation($woB), 'Cutting', 1); // different job — untouched

        $res = $this->postJson('/api/v1/work-order-stages/bulk-assign', [
            'job_id'      => $this->job->id,
            'assignments' => [
                ['stage_name' => 'Cutting', 'assigned_to_id' => $al->id],
                ['stage_name' => 'Programming', 'assigned_to_id' => $bob->id],
            ],
        ])->assertOk();

        $this->assertSame(['Cutting' => 1, 'Programming' => 1], $res->json('by_stage'));
        $this->assertSame($al->id, $cutA->fresh()->assigned_to_id);
        $this->assertSame($bob->id, $progA->fresh()->assigned_to_id);
        $this->assertNull($cutB->fresh()->assigned_to_id);
    }

    public function test_bulk_assign_requires_fabrication_edit_permission(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'no-perms-role', 'is_active' => true]), ['*']);
        $wo = $this->wo();
        $this->postJson('/api/v1/work-order-stages/bulk-assign', [
            'work_order_id' => $wo->id,
            'assignments'   => [['stage_name' => 'Cutting', 'assigned_to_id' => null]],
        ])->assertStatus(403);
    }
}
