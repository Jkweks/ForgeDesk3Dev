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

/** GET /api/v1/programming-queue — elevations whose programming step is still to do. */
class ProgrammingQueueTest extends TestCase
{
    use RefreshDatabase;

    private BusinessJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->job = BusinessJob::create(['job_number' => 'J1', 'job_name' => 'Job One', 'status' => 'active']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
    }

    private function wo(array $attr = []): FdWorkOrder
    {
        static $rel = 0;

        return FdWorkOrder::create(array_merge([
            'business_job_id' => $this->job->id,
            'release_number' => ++$rel,
        ], $attr));
    }

    private function line(FdWorkOrder $wo, string $tag, array $steps): array
    {
        $e = FdWoElevation::create(['work_order_id' => $wo->id, 'elevation_tag' => $tag]);
        $out = [];
        foreach ($steps as $i => $spec) {
            [$name, $status] = $spec;
            $out[$name] = FdWoStage::create([
                'elevation_id' => $e->id, 'name' => $name, 'sort_order' => $i + 1,
                'blocks_next' => true, 'status' => $status,
            ]);
        }

        return $out;
    }

    private function ids(array $json): array
    {
        return collect($json['rows'])->pluck('stage_id')->all();
    }

    public function test_lists_only_programming_steps_that_are_still_open(): void
    {
        $wo = $this->wo();
        $a = $this->line($wo, 'D1', [['Programmed', 'pending'], ['CNC', 'pending']]);
        $b = $this->line($wo, 'D2', [['CNC Programming', 'in_progress']]);
        $c = $this->line($wo, 'D3', [['Program', 'complete'], ['CNC', 'pending']]);
        $d = $this->line($wo, 'D4', [['Cut', 'pending']]);

        $ids = $this->ids($this->getJson('/api/v1/programming-queue')->assertOk()->json());

        $this->assertEqualsCanonicalizing([$a['Programmed']->id, $b['CNC Programming']->id], $ids);
        $this->assertNotContains($c['Program']->id, $ids);
        $this->assertNotContains($d['Cut']->id, $ids);
    }

    public function test_in_progress_ranks_before_pending_then_by_work_order_priority(): void
    {
        $low = $this->wo(['priority' => 5]);
        $high = $this->wo(['priority' => 1]);
        $lowLine = $this->line($low, 'L', [['Programmed', 'pending']]);
        $highLine = $this->line($high, 'H', [['Programmed', 'pending']]);
        $busy = $this->line($low, 'B', [['Programmed', 'in_progress']]);

        $ids = $this->ids($this->getJson('/api/v1/programming-queue')->json());

        $this->assertSame(
            [$busy['Programmed']->id, $highLine['Programmed']->id, $lowLine['Programmed']->id],
            $ids
        );
    }

    public function test_archived_work_orders_and_other_jobs_are_excluded_by_filter(): void
    {
        $live = $this->wo();
        $archived = $this->wo(['archived' => true]);
        $other = BusinessJob::create(['job_number' => 'J2', 'job_name' => 'Two', 'status' => 'active']);
        $elsewhere = $this->wo(['business_job_id' => $other->id]);

        $a = $this->line($live, 'A', [['Programmed', 'pending']]);
        $this->line($archived, 'X', [['Programmed', 'pending']]);
        $c = $this->line($elsewhere, 'C', [['Programmed', 'pending']]);

        $this->assertEqualsCanonicalizing(
            [$a['Programmed']->id, $c['Programmed']->id],
            $this->ids($this->getJson('/api/v1/programming-queue')->json())
        );
        $this->assertSame(
            [$a['Programmed']->id],
            $this->ids($this->getJson('/api/v1/programming-queue?job_id='.$this->job->id)->json())
        );
    }

    public function test_include_done_adds_recently_completed_programming_only(): void
    {
        $wo = $this->wo();
        $recent = $this->line($wo, 'R', [['Programmed', 'complete']]);
        $recent['Programmed']->update(['completed_at' => now()->subDay()]);
        $old = $this->line($wo, 'O', [['Programmed', 'complete']]);
        $old['Programmed']->update(['completed_at' => now()->subDays(30)]);

        $this->assertSame([], $this->ids($this->getJson('/api/v1/programming-queue')->json()));
        $this->assertSame(
            [$recent['Programmed']->id],
            $this->ids($this->getJson('/api/v1/programming-queue?include_done=1')->json())
        );
    }

    public function test_row_carries_assignee_and_downstream_steps(): void
    {
        $fab = FdUser::create(['name' => 'Pat', 'initials' => 'PT', 'role' => 'worker', 'active' => true]);
        $s = $this->line($this->wo(), 'D1', [['Programmed', 'pending'], ['CNC', 'pending']]);
        $s['Programmed']->syncAssignees([$fab->id]);

        $row = $this->getJson('/api/v1/programming-queue')->json('rows.0');

        $this->assertSame([$fab->id], $row['assignee_ids']);
        $this->assertSame(['Programmed', 'CNC'], array_column($row['steps'], 'name'));
    }

    public function test_new_programming_steps_default_to_the_configured_programmer(): void
    {
        $jon = FdUser::create(['name' => 'Jon Kwekel', 'initials' => 'JK', 'role' => 'admin', 'active' => true]);
        $other = FdUser::create(['name' => 'Pat', 'initials' => 'PT', 'role' => 'worker', 'active' => true]);
        $wo = $this->wo();

        $s = $this->line($wo, 'D1', [['Programmed', 'pending'], ['CNC', 'pending'], ['Program', 'pending']]);
        $e = FdWoElevation::first();
        $explicit = FdWoStage::create([
            'elevation_id' => $e->id, 'name' => 'CNC Programming', 'sort_order' => 9, 'assigned_to_id' => $other->id,
        ]);

        $this->assertSame([$jon->id], $s['Programmed']->assignees()->pluck('fd_users.id')->all());
        $this->assertSame($jon->id, $s['Programmed']->fresh()->assigned_to_id);
        $this->assertSame([$jon->id], $s['Program']->assignees()->pluck('fd_users.id')->all());
        // Non-programming steps and explicit assignees are left alone
        $this->assertNull($s['CNC']->fresh()->assigned_to_id);
        $this->assertSame($other->id, $explicit->fresh()->assigned_to_id);
    }

    public function test_programming_is_due_the_working_day_before_planned_fab_start(): void
    {
        // 2026-11-02 is a Monday: programming is due the Friday before. 2026-11-04 (Wed) -> Tuesday.
        $mon = $this->wo(['planned_start_date' => '2026-11-02']);
        $wed = $this->wo(['planned_start_date' => '2026-11-04']);
        $none = $this->wo();
        $a = $this->line($mon, 'M', [['Programmed', 'pending']]);
        $b = $this->line($wed, 'W', [['Programmed', 'pending']]);
        $c = $this->line($none, 'N', [['Programmed', 'pending']]);

        $rows = collect($this->getJson('/api/v1/programming-queue')->json('rows'))->keyBy('stage_id');

        $this->assertSame('2026-10-30', $rows[$a['Programmed']->id]['programming_date']);
        $this->assertSame('2026-11-03', $rows[$b['Programmed']->id]['programming_date']);
        $this->assertNull($rows[$c['Programmed']->id]['programming_date']);
        $this->assertSame('2026-11-02', $rows[$a['Programmed']->id]['planned_start_date']);
    }

    public function test_programming_minutes_come_from_the_step_rate_and_joint_count(): void
    {
        $s = $this->line($this->wo(), 'D1', [['Programmed', 'pending']]);
        $s['Programmed']->update(['minutes_per_joint' => 7.5]);
        FdWoElevation::first()->update(['joint_qty' => 4]);

        $row = $this->getJson('/api/v1/programming-queue')->json('rows.0');

        $this->assertSame(30, $row['programming_minutes']);
        $this->assertSame(30, $row['fab_estimated_minutes']);
    }

    public function test_page_renders(): void
    {
        $this->get('/fabrication/programming-queue')->assertOk()->assertSee('Programming Queue');
    }
}
