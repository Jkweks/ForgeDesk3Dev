<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\FdScheduleEntry;
use App\Models\FdUser;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Models\FdWoStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Work Queue schedule: assists / notes on an operator's row, quarter-day planned dates,
 * and the data the schedule view and dashboard widget read.
 */
class ScheduleEntryTest extends TestCase
{
    use RefreshDatabase;

    private FdUser $alice;

    private FdUser $bob;

    private FdWorkOrder $wo;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->alice = FdUser::create(['name' => 'Alice', 'initials' => 'AL', 'role' => 'worker', 'active' => true]);
        $this->bob = FdUser::create(['name' => 'Bob', 'initials' => 'BO', 'role' => 'worker', 'active' => true]);
        $job = BusinessJob::create(['job_number' => 'J1', 'job_name' => 'Algoma', 'status' => 'active']);
        $this->wo = FdWorkOrder::create(['business_job_id' => $job->id, 'release_number' => 1]);
    }

    private function stage(string $name, ?FdUser $user, string $type = 'SF'): FdWoStage
    {
        $typeId = \DB::table('fd_elevation_types')->where('name', $type)->value('id')
            ?? \DB::table('fd_elevation_types')->insertGetId(['name' => $type, 'created_at' => now(), 'updated_at' => now()]);
        $e = FdWoElevation::create(['work_order_id' => $this->wo->id, 'elevation_tag' => $type.'1', 'elevation_type_id' => $typeId]);

        return FdWoStage::create(['elevation_id' => $e->id, 'name' => $name, 'sort_order' => 1, 'blocks_next' => true, 'status' => 'pending', 'assigned_to_id' => $user?->id]);
    }

    public function test_assist_and_note_can_be_created_updated_and_removed(): void
    {
        $assist = $this->postJson('/api/v1/schedule-entries', [
            'user_id' => $this->bob->id, 'work_order_id' => $this->wo->id, 'start_date' => '2026-10-27', 'end_date' => '2026-10-27',
        ])->assertCreated()->json('id');
        $note = $this->postJson('/api/v1/schedule-entries', [
            'user_id' => $this->alice->id, 'start_date' => '2026-10-28', 'end_date' => '2026-10-28', 'note' => 'Day off',
        ])->assertCreated()->json('id');

        // extend the assist to mid-day Wednesday (quarter 1 = first 4 hours)
        $this->patchJson("/api/v1/schedule-entries/{$assist}", ['end_date' => '2026-10-28', 'end_quarter' => 1])->assertOk();
        $row = FdScheduleEntry::find($assist);
        $this->assertSame('2026-10-28', $row->end_date->format('Y-m-d'));
        $this->assertSame(1, (int) $row->end_quarter);
        $this->assertSame(0, (int) $row->start_quarter); // untouched default

        // move the note to another operator
        $this->patchJson("/api/v1/schedule-entries/{$note}", ['user_id' => $this->bob->id])->assertOk();
        $this->assertSame($this->bob->id, FdScheduleEntry::find($note)->user_id);

        $this->deleteJson("/api/v1/schedule-entries/{$assist}")->assertOk();
        $this->assertNull(FdScheduleEntry::find($assist));
    }

    public function test_validation_rules(): void
    {
        $base = ['user_id' => $this->alice->id, 'start_date' => '2026-10-27', 'end_date' => '2026-10-27'];

        $this->postJson('/api/v1/schedule-entries', $base)->assertStatus(422);                       // a note needs text
        $this->postJson('/api/v1/schedule-entries', ['end_date' => '2026-10-26'] + $base + ['note' => 'x'])->assertStatus(422); // ends before it starts
        $this->postJson('/api/v1/schedule-entries', $base + ['note' => 'x', 'end_quarter' => 4])->assertStatus(422);
        $this->postJson('/api/v1/schedule-entries', ['user_id' => 9999] + $base + ['note' => 'x'])->assertStatus(422);

        $id = $this->postJson('/api/v1/schedule-entries', $base + ['note' => 'Out early'])->json('id');
        // same day: finishing in an earlier quarter than it starts is rejected
        $this->patchJson("/api/v1/schedule-entries/{$id}", ['start_quarter' => 2, 'end_quarter' => 1])->assertStatus(422);
    }

    public function test_viewer_cannot_change_the_schedule(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]), ['*']);

        $this->postJson('/api/v1/schedule-entries', [
            'user_id' => $this->alice->id, 'start_date' => '2026-10-27', 'end_date' => '2026-10-27', 'note' => 'x',
        ])->assertForbidden();
    }

    public function test_deleting_a_work_order_removes_its_assists(): void
    {
        $entry = FdScheduleEntry::create(['user_id' => $this->bob->id, 'work_order_id' => $this->wo->id, 'start_date' => '2026-10-27', 'end_date' => '2026-10-27']);

        \DB::table('fd_work_orders')->where('id', $this->wo->id)->delete();

        $this->assertNull(FdScheduleEntry::find($entry->id));
    }

    public function test_work_order_patch_stores_quarters_and_a_plain_date_resets_them(): void
    {
        $this->patchJson("/api/v1/work-orders/{$this->wo->id}", [
            'planned_start_date' => '2026-10-27', 'planned_start_quarter' => 2,
            'planned_completion_date' => '2026-10-29', 'planned_completion_quarter' => 1,
        ])->assertOk();
        $wo = $this->wo->fresh();
        $this->assertSame([2, 1], [(int) $wo->planned_start_quarter, (int) $wo->planned_completion_quarter]);

        // Setting a date from anywhere else (no quarter sent) means the whole day again
        $this->patchJson("/api/v1/work-orders/{$this->wo->id}", ['planned_start_date' => '2026-10-28'])->assertOk();
        $wo = $this->wo->fresh();
        $this->assertSame([0, 1], [(int) $wo->planned_start_quarter, (int) $wo->planned_completion_quarter]);
    }

    public function test_work_queue_payload_carries_schedule_fields(): void
    {
        $this->wo->update(['planned_start_date' => '2026-10-27', 'planned_start_quarter' => 2, 'planned_completion_date' => '2026-10-28', 'planned_completion_quarter' => 1]);
        $this->stage('Cut', $this->alice, 'SF');
        $this->stage('Cut', $this->alice, 'Door');
        FdScheduleEntry::create(['user_id' => $this->bob->id, 'work_order_id' => $this->wo->id, 'start_date' => '2026-10-27', 'end_date' => '2026-10-27', 'start_quarter' => 1, 'end_quarter' => 2]);

        $res = $this->getJson('/api/v1/work-queue')->assertOk();

        $card = $res->json('operators.0.stages.0');
        $this->assertSame(2, $card['planned_start_quarter']);
        $this->assertSame(1, $card['planned_completion_quarter']);
        $this->assertEqualsCanonicalizing(['SF', 'Door'], $card['stage_scopes']);
        $this->assertCount(2, $card['stage_ids']);
        $this->assertArrayHasKey('estimated_minutes', $card);

        $entry = $res->json('schedule_entries.0');
        $this->assertSame($this->bob->id, $entry['user_id']);
        $this->assertSame([1, 2], [$entry['start_quarter'], $entry['end_quarter']]);
        $this->assertSame('Algoma (R1)', $entry['label']);
    }

    public function test_dashboard_week_widget_returns_bars_for_the_current_work_week(): void
    {
        $monday = today()->startOfWeek();
        $this->wo->update(['planned_start_date' => $monday->copy()->addDay(), 'planned_start_quarter' => 2, 'planned_completion_date' => $monday->copy()->addDays(2), 'planned_completion_quarter' => 1]);
        $this->stage('Cut', $this->alice);
        FdScheduleEntry::create(['user_id' => $this->bob->id, 'start_date' => $monday, 'end_date' => $monday, 'note' => 'Dentist']);
        FdScheduleEntry::create(['user_id' => $this->bob->id, 'start_date' => $monday->copy()->subWeeks(3), 'end_date' => $monday->copy()->subWeeks(3), 'note' => 'Long ago']);

        $res = $this->getJson('/api/v1/dashboard/widgets/work-orders/schedule')->assertOk();

        $this->assertSame([$monday->format('Y-m-d'), $monday->copy()->addDays(4)->format('Y-m-d')], [$res->json('days.0.key'), $res->json('days.4.key')]);
        $users = collect($res->json('users'))->keyBy('name');
        $this->assertSame([['text' => 'Algoma', 'kind' => 'job', 'start' => $monday->copy()->addDay()->format('Y-m-d'), 'sq' => 2, 'end' => $monday->copy()->addDays(2)->format('Y-m-d'), 'eq' => 1]], $users['Alice']['bars']);
        $this->assertSame(['Dentist'], collect($users['Bob']['bars'])->pluck('text')->all()); // last month's note is not in this week
    }

    public function test_dashboard_week_widget_requires_work_order_permission(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'no-such-role', 'is_active' => true]), ['*']);

        $this->getJson('/api/v1/dashboard/widgets/work-orders/schedule')->assertForbidden();
    }
}
