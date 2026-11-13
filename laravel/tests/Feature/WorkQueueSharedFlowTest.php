<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\FdUser;
use App\Models\FdWoElevation;
use App\Models\FdWoOperatorSchedule;
use App\Models\FdWorkOrder;
use App\Models\FdWoStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Work Queue with a work order shared by several operators: reassigning one
 * operator leaves the others alone, and each operator has their own dates.
 */
class WorkQueueSharedFlowTest extends TestCase
{
    use RefreshDatabase;

    private FdUser $alice;

    private FdUser $bob;

    private FdUser $carol;

    private FdWorkOrder $wo;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        foreach (['alice' => 'AL', 'bob' => 'BO', 'carol' => 'CA'] as $name => $initials) {
            $this->{$name} = FdUser::create(['name' => ucfirst($name), 'initials' => $initials, 'role' => 'worker', 'active' => true]);
        }
        $job = BusinessJob::create(['job_number' => 'J1', 'job_name' => 'Algoma', 'status' => 'active']);
        $this->wo = FdWorkOrder::create(['business_job_id' => $job->id, 'release_number' => 1]);
    }

    private function stage(string $name, array $users, int $order = 1): FdWoStage
    {
        $e = FdWoElevation::create(['work_order_id' => $this->wo->id, 'elevation_tag' => 'E'.$order.$name]);
        $s = FdWoStage::create(['elevation_id' => $e->id, 'name' => $name, 'sort_order' => $order, 'blocks_next' => false, 'status' => 'pending']);
        $s->syncAssignees(array_map(fn ($u) => $u->id, $users));

        return $s;
    }

    private function ids(FdWoStage $s): array
    {
        return $s->fresh()->assignees()->pluck('fd_users.id')->sort()->values()->all();
    }

    public function test_moving_one_operator_off_shared_steps_keeps_each_steps_other_assignees(): void
    {
        $cut = $this->stage('Cut', [$this->alice, $this->bob]);
        $weld = $this->stage('Weld', [$this->alice], 2);

        // Alice's bar (both steps) dragged to Carol's row
        $this->postJson('/api/v1/work-queue/reassign', [
            'stage_ids' => [$cut->id, $weld->id],
            'remove_user_id' => $this->alice->id,
            'add_user_ids' => [$this->carol->id],
        ])->assertOk()->assertJson(['updated' => 2]);

        $this->assertSame([$this->bob->id, $this->carol->id], $this->ids($cut));   // Bob still shares Cut
        $this->assertSame([$this->carol->id], $this->ids($weld));                  // Weld did not pick up Bob
        $this->assertSame(2, $cut->log()->count() + $weld->log()->count());
    }

    public function test_add_only_shares_and_set_replaces(): void
    {
        $cut = $this->stage('Cut', [$this->alice]);

        $this->postJson('/api/v1/work-queue/reassign', ['stage_ids' => [$cut->id], 'add_user_ids' => [$this->bob->id]])->assertOk();
        $this->assertSame([$this->alice->id, $this->bob->id], $this->ids($cut));

        $this->postJson('/api/v1/work-queue/reassign', ['stage_ids' => [$cut->id], 'set_user_ids' => []])->assertOk();
        $this->assertSame([], $this->ids($cut));
        $this->assertNull($cut->fresh()->assigned_to_id);
    }

    public function test_each_operator_gets_their_own_window_and_the_work_order_spans_them(): void
    {
        $this->wo->update(['planned_start_date' => '2026-11-16', 'planned_completion_date' => '2026-11-17']);
        $this->stage('Cut', [$this->alice]);
        $this->stage('Weld', [$this->bob], 2);

        // Bob's bar moved later in the week; Alice must stay where she was
        $this->putJson("/api/v1/work-queue/work-orders/{$this->wo->id}/operators/{$this->bob->id}/schedule", [
            'start_date' => '2026-11-19', 'end_date' => '2026-11-20', 'end_quarter' => 1,
        ])->assertOk();

        $alice = FdWoOperatorSchedule::where('user_id', $this->alice->id)->first();
        $this->assertSame('2026-11-16', $alice->start_date->format('Y-m-d'));
        $this->assertSame('2026-11-17', $alice->end_date->format('Y-m-d'));

        $wo = $this->wo->fresh();
        $this->assertSame('2026-11-16', $wo->planned_start_date->format('Y-m-d'));
        $this->assertSame('2026-11-20', $wo->planned_completion_date->format('Y-m-d'));
        $this->assertSame(1, (int) $wo->planned_completion_quarter);

        $windows = collect($this->getJson('/api/v1/work-queue')->assertOk()->json('operator_windows'));
        $this->assertCount(2, $windows);
        $this->assertSame('2026-11-19', $windows->firstWhere('user_id', $this->bob->id)['start_date']);
    }

    public function test_handing_a_work_order_over_carries_the_window_and_drops_the_old_one(): void
    {
        $cut = $this->stage('Cut', [$this->alice]);
        $this->putJson("/api/v1/work-queue/work-orders/{$this->wo->id}/operators/{$this->alice->id}/schedule", [
            'start_date' => '2026-11-18', 'end_date' => '2026-11-18',
        ])->assertOk();

        $this->postJson('/api/v1/work-queue/reassign', [
            'stage_ids' => [$cut->id], 'remove_user_id' => $this->alice->id, 'add_user_ids' => [$this->bob->id],
        ])->assertOk();

        $this->assertFalse(FdWoOperatorSchedule::where('user_id', $this->alice->id)->exists());
        $this->assertSame('2026-11-18', FdWoOperatorSchedule::where('user_id', $this->bob->id)->first()->start_date->format('Y-m-d'));
    }

    public function test_setting_planned_dates_on_the_work_order_resets_operator_windows(): void
    {
        $this->stage('Cut', [$this->alice]);
        $this->putJson("/api/v1/work-queue/work-orders/{$this->wo->id}/operators/{$this->alice->id}/schedule", [
            'start_date' => '2026-11-18', 'end_date' => '2026-11-18',
        ])->assertOk();

        // Touching something else leaves the windows alone
        $this->patchJson("/api/v1/work-orders/{$this->wo->id}", ['due_date' => '2026-11-30'])->assertOk();
        $this->assertSame(1, FdWoOperatorSchedule::count());

        $this->patchJson("/api/v1/work-orders/{$this->wo->id}", ['planned_start_date' => '2026-11-23', 'planned_completion_date' => '2026-11-24'])->assertOk();
        $this->assertSame(0, FdWoOperatorSchedule::count());
    }

    public function test_a_card_lists_everyone_on_any_of_its_stages(): void
    {
        $this->stage('Cut', [$this->alice]);
        $this->stage('Cut', [$this->alice, $this->bob], 2);

        $ops = collect($this->getJson('/api/v1/work-queue')->assertOk()->json('operators'))->keyBy('user.id');
        $card = $ops[$this->alice->id]['stages'][0];
        $this->assertCount(2, $card['stage_ids']);
        $this->assertEqualsCanonicalizing([$this->alice->id, $this->bob->id], $card['assignee_ids']);
    }
}
