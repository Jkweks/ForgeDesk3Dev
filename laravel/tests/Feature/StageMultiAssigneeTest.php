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
 * A stage can be assigned to several operators at once. It shows in each of
 * their queues, and — because status lives on the single stage row — whoever
 * moves it to a terminal status clears it for everyone.
 */
class StageMultiAssigneeTest extends TestCase
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
            'priority'        => 1,
            'priority_locked' => true,
        ], $attr));
    }

    private function elevation(FdWorkOrder $wo, ?string $requested = null): FdWoElevation
    {
        return FdWoElevation::create([
            'work_order_id'  => $wo->id,
            'elevation_tag'  => 'E' . $wo->id . '-' . uniqid(),
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

    public function test_patching_assigned_to_ids_puts_the_stage_in_every_operator_column(): void
    {
        $al  = FdUser::create(['name' => 'Al', 'initials' => 'AL', 'role' => 'worker', 'active' => true]);
        $mo  = FdUser::create(['name' => 'Mo', 'initials' => 'MO', 'role' => 'worker', 'active' => true]);

        $s = $this->stage($this->elevation($this->wo()), 'Door assembly', 1);

        $this->patchJson("/api/v1/work-order-stages/{$s->id}", [
            'assigned_to_ids' => [$al->id, $mo->id],
        ])->assertOk();

        $this->assertEqualsCanonicalizing([$al->id, $mo->id], $s->assignees()->pluck('fd_users.id')->all());
        $this->assertSame($al->id, $s->fresh()->assigned_to_id); // primary mirror = first

        $ops = collect($this->getJson('/api/v1/work-queue')->json('operators'))->keyBy('user.id');
        $this->assertSame(['Door assembly'], collect($ops[$al->id]['stages'])->pluck('name')->all());
        $this->assertSame(['Door assembly'], collect($ops[$mo->id]['stages'])->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['Al', 'Mo'], $ops[$al->id]['stages'][0]['assignee_names']);
    }

    public function test_one_operator_completing_clears_it_for_all(): void
    {
        $al = FdUser::create(['name' => 'Al', 'role' => 'worker', 'active' => true]);
        $mo = FdUser::create(['name' => 'Mo', 'role' => 'worker', 'active' => true]);
        $s  = $this->stage($this->elevation($this->wo()), 'Door assembly', 1);
        $s->syncAssignees([$al->id, $mo->id]);

        // Al marks it complete from the kiosk (PATCH cycles the status forward).
        $this->patchJson("/api/v1/shop/stages/{$s->id}", ['fab_user_id' => $al->id]); // pending -> in_progress
        $this->patchJson("/api/v1/shop/stages/{$s->id}", ['fab_user_id' => $al->id]); // in_progress -> complete
        $this->assertSame('complete', $s->fresh()->status);

        // Gone from both operators' queues (actionable excludes terminal).
        foreach ([$al, $mo] as $u) {
            $this->assertSame([], $this->getJson("/api/v1/shop/my-queue?fab_user_id={$u->id}")->json('queue'));
        }
    }

    public function test_my_queue_includes_a_shared_stage_for_each_assignee(): void
    {
        $al = FdUser::create(['name' => 'Al', 'role' => 'worker', 'active' => true]);
        $mo = FdUser::create(['name' => 'Mo', 'role' => 'worker', 'active' => true]);
        $s  = $this->stage($this->elevation($this->wo(), '2026-09-01'), 'Glaze', 1);
        $s->syncAssignees([$al->id, $mo->id]);

        foreach ([$al, $mo] as $u) {
            $q = $this->getJson("/api/v1/shop/my-queue?fab_user_id={$u->id}")->json('queue');
            $this->assertSame(['Glaze'], collect($q)->pluck('name')->all());
            $this->assertEqualsCanonicalizing(['Al', 'Mo'], $q[0]['assignee_names']);
        }
    }

    public function test_single_assigned_to_id_patch_still_syncs_the_pivot(): void
    {
        $al = FdUser::create(['name' => 'Al', 'role' => 'worker', 'active' => true]);
        $mo = FdUser::create(['name' => 'Mo', 'role' => 'worker', 'active' => true]);
        $s  = $this->stage($this->elevation($this->wo()), 'Weld', 1);
        $s->syncAssignees([$al->id, $mo->id]);

        // Drag-drop path sends a single id — it should REPLACE the whole set.
        $this->patchJson("/api/v1/work-order-stages/{$s->id}", ['assigned_to_id' => $mo->id])->assertOk();
        $this->assertSame([$mo->id], $s->assignees()->pluck('fd_users.id')->all());

        // Dropping on "Unassigned" clears everyone.
        $this->patchJson("/api/v1/work-order-stages/{$s->id}", ['assigned_to_id' => null])->assertOk();
        $this->assertSame([], $s->assignees()->pluck('fd_users.id')->all());
        $this->assertNull($s->fresh()->assigned_to_id);
    }

    public function test_bulk_assign_can_share_a_step_across_a_work_orders_elevations(): void
    {
        $al = FdUser::create(['name' => 'Al', 'role' => 'worker', 'active' => true]);
        $mo = FdUser::create(['name' => 'Mo', 'role' => 'worker', 'active' => true]);

        $wo = $this->wo();
        $a1 = $this->stage($this->elevation($wo), 'Assembly', 1);
        $a2 = $this->stage($this->elevation($wo), 'Assembly', 1);

        $res = $this->postJson('/api/v1/work-order-stages/bulk-assign', [
            'work_order_id' => $wo->id,
            'assignments'   => [
                ['stage_name' => 'Assembly', 'assigned_to_ids' => [$al->id, $mo->id]],
            ],
        ])->assertOk();

        $this->assertSame(2, $res->json('updated'));
        $this->assertEqualsCanonicalizing([$al->id, $mo->id], $a1->assignees()->pluck('fd_users.id')->all());
        $this->assertEqualsCanonicalizing([$al->id, $mo->id], $a2->assignees()->pluck('fd_users.id')->all());

        // Re-running with the same set is a no-op.
        $again = $this->postJson('/api/v1/work-order-stages/bulk-assign', [
            'work_order_id' => $wo->id,
            'assignments'   => [['stage_name' => 'Assembly', 'assigned_to_ids' => [$mo->id, $al->id]]],
        ])->assertOk();
        $this->assertSame(0, $again->json('updated'));
    }
}
