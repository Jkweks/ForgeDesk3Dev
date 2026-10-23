<?php

namespace Tests\Feature;

use App\Dashboard\WidgetRegistry;
use App\Models\CutFlow\CutJob;
use App\Models\CutFlow\CutLogEntry;
use App\Models\CutFlow\StickSession;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CutStationStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // CutFlow tables live on their own connection, which RefreshDatabase does not migrate.
        if (! Schema::connection('cutflow')->hasTable('cut_log_entries')) {
            if (! Schema::connection('cutflow')->hasTable('cut_log_entries')) {
            $this->artisan('migrate', ['--database' => 'cutflow', '--path' => 'database/migrations/cutflow', '--force' => true]);
        }
        }

        // The migration that adds the 'drop' log type only widens the CHECK on Postgres; on this
        // SQLite copy drop the constraint so drop rows can be inserted.
        Schema::connection('cutflow')->table('cut_log_entries', fn ($table) => $table->string('type', 10)->default('planned')->change());

        // 2026-06-15 15:00 UTC is 11:00 in the shop (EDT).
        Carbon::setTestNow('2026-06-15 15:00:00 UTC');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function entry(string $at, array $attrs = []): CutLogEntry
    {
        $entry = new CutLogEntry($attrs + [
            'part_name' => 'Rail', 'dimension_inches' => 10, 'type' => 'planned',
            'operator_id' => 1, 'operator_name' => 'Ann',
        ]);
        $entry->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

        return $entry;
    }

    public function test_total_cuts_by_day_month_and_year_uses_shop_calendar_and_skips_drops_and_reprints(): void
    {
        $this->admin();

        $this->entry('2026-06-15 14:00:00');                              // today
        $this->entry('2026-06-15 14:30:00', ['type' => 'manual']);        // today
        $this->entry('2026-06-15 14:45:00', ['is_recut' => true]);        // a recut flag, not a cut: the redo logs as a cut
        $this->entry('2026-06-15 14:10:00', ['type' => 'drop']);          // not a cut
        $this->entry('2026-06-15 14:20:00', ['is_reprint' => true]);      // not a cut
        $this->entry('2026-06-15 03:00:00');                              // 23:00 the day before, shop time
        $this->entry('2026-06-02 12:00:00');                              // earlier this month
        $this->entry('2026-02-10 12:00:00');                              // earlier this year
        $this->entry('2026-01-01 03:00:00');                              // 22:00 Dec 31, shop time: last year
        $this->entry('2025-07-01 12:00:00');                              // last year

        $this->getJson('/api/v1/dashboard/widgets/cutflow/cuts?period=day')
            ->assertOk()->assertJson(['available' => true, 'period' => 'day', 'caption' => 'Jun 15, 2026', 'total' => 2]);
        $this->getJson('/api/v1/dashboard/widgets/cutflow/cuts?period=month')
            ->assertOk()->assertJson(['caption' => 'June 2026', 'total' => 4]);
        $this->getJson('/api/v1/dashboard/widgets/cutflow/cuts?period=year')
            ->assertOk()->assertJson(['caption' => '2026', 'total' => 5]);
    }

    public function test_widget_period_defaults_to_day_for_missing_or_invalid_values(): void
    {
        $this->admin();
        $this->entry('2026-06-15 14:00:00');
        $this->entry('2026-06-10 14:00:00');

        $this->getJson('/api/v1/dashboard/widgets/cutflow/cuts')->assertOk()->assertJson(['period' => 'day', 'total' => 1]);
        $this->getJson('/api/v1/dashboard/widgets/cutflow/cuts?period=decade')->assertOk()->assertJson(['period' => 'day', 'total' => 1]);
        $this->assertSame('day', WidgetRegistry::resolveSettings('cutflow_cuts_total', [])['period']);
    }

    public function test_productivity_report_groups_by_operator_and_excludes_drops(): void
    {
        $this->admin();
        $this->entry('2026-06-15 14:00:00');
        $this->entry('2026-06-15 14:05:00');
        $this->entry('2026-06-14 14:05:00', ['is_manual_cut' => true]);
        $this->entry('2026-06-14 14:10:00', ['is_recut' => true]);
        $this->entry('2026-06-15 14:00:00', ['operator_id' => 2, 'operator_name' => 'Bob']);
        $this->entry('2026-06-15 14:00:00', ['operator_id' => null, 'operator_name' => null]);
        $this->entry('2026-06-15 14:00:00', ['type' => 'drop']);

        $r = $this->getJson('/api/v1/reports/cut-station/productivity?start_date=2026-06-01&end_date=2026-06-30')
            ->assertOk()->json();

        $this->assertSame(5, $r['summary']['total_cuts']);
        $this->assertSame(3, $r['summary']['operators']);
        $this->assertSame(2, $r['summary']['active_days']);
        $this->assertSame('2026-06-15', $r['summary']['busiest_day']);

        $ann = $r['by_operator'][0];
        $this->assertEquals(['Ann', 3, 1, 33.3, 1, 2], [$ann['operator'], $ann['cuts'], $ann['recuts'], $ann['recut_rate'], $ann['manual_cuts'], $ann['active_days']]);
        $this->assertEquals(1.5, $ann['avg_per_active_day']);
        $this->assertContains('Not signed in', array_column($r['by_operator'], 'operator'));

        // 14:00 UTC is 10:00 shop time
        $this->assertSame(5, $r['by_hour'][10]['cuts']);
        $this->assertSame(0, $r['by_hour'][14]['cuts']);
    }

    public function test_recut_report_ranks_jobs_operators_and_profiles(): void
    {
        $this->admin();
        $job = CutJob::create(['name' => 'Job A']);
        foreach (range(1, 3) as $i) {
            $this->entry('2026-06-15 14:00:00', ['job_name' => 'Job A', 'cut_job_id' => $job->id]);
        }
        $this->entry('2026-06-15 14:00:00', ['job_name' => 'Job A', 'cut_job_id' => $job->id, 'is_recut' => true]);
        $this->entry('2026-06-15 14:00:00', ['job_name' => null, 'part_name' => 'Jamb', 'operator_id' => 2, 'operator_name' => 'Bob']);

        $r = $this->getJson('/api/v1/reports/cut-station/recuts?start_date=2026-06-15&end_date=2026-06-15')->assertOk()->json();

        $this->assertSame(4, $r['summary']['total_cuts']);
        $this->assertSame(1, $r['summary']['recuts']);
        $this->assertEquals(25.0, $r['summary']['recut_rate']);
        $this->assertSame(['Job A', 3, 1], [$r['by_job'][0]['job'], $r['by_job'][0]['cuts'], $r['by_job'][0]['recuts']]);
        $this->assertSame('Manual / no cut list', $r['by_job'][1]['job']);
        $this->assertSame(['Rail', 3, 1], [$r['by_profile'][0]['part_name'], $r['by_profile'][0]['cuts'], $r['by_profile'][0]['recuts']]);
        $this->assertSame('Ann', $r['by_operator'][0]['operator']);
    }

    public function test_waste_report_separates_leftover_rack_drops_and_scrap(): void
    {
        $this->admin();
        foreach ([[240, 20], [240, 10]] as [$length, $waste]) {
            $stick = new StickSession(['length_label' => '20ft', 'part_name' => 'Rail', 'length_inches' => $length, 'waste_inches' => $waste, 'status' => 'complete']);
            $stick->forceFill(['updated_at' => '2026-06-15 14:00:00'])->save();
        }
        $cancelled = new StickSession(['length_label' => '20ft', 'part_name' => 'Rail', 'length_inches' => 240, 'waste_inches' => 99, 'status' => 'complete', 'cancelled_at' => now()]);
        $cancelled->forceFill(['updated_at' => '2026-06-15 14:00:00'])->save();

        $this->entry('2026-06-15 14:00:00', ['type' => 'drop', 'description' => 'Drop (not cut)', 'dimension_inches' => 18]);
        $this->entry('2026-06-15 14:00:00', ['type' => 'drop', 'description' => 'Drop cut', 'dimension_inches' => 30]);
        $this->entry('2026-06-15 14:00:00', ['type' => 'drop', 'description' => 'Scrap - too short', 'dimension_inches' => 4]);
        $this->entry('2026-06-15 14:00:00'); // a plain cut is not material waste

        $r = $this->getJson('/api/v1/reports/cut-station/waste?start_date=2026-06-15&end_date=2026-06-15')->assertOk()->json();

        $this->assertCount(1, $r['by_profile']);
        $this->assertSame(2, $r['summary']['sticks']);
        $this->assertEquals(480.0, $r['summary']['stock_inches']);
        $this->assertEquals(30.0, $r['summary']['leftover_inches']);
        $this->assertSame(6.3, $r['summary']['leftover_percent']);
        $this->assertSame(2, $r['summary']['rack_drops']);
        $this->assertSame(1, $r['summary']['scrap_pieces']);
        $this->assertEquals(4.0, $r['summary']['scrap_inches']);
    }

    public function test_csv_export_streams_operator_rows(): void
    {
        $this->admin();
        $this->entry('2026-06-15 14:00:00');

        $res = $this->get('/api/v1/reports/export?type=cut_station_productivity&start_date=2026-06-15&end_date=2026-06-15');

        $res->assertOk();
        $csv = $res->streamedContent();
        $this->assertStringContainsString('Operator,Cuts,Recuts', $csv);
        $this->assertStringContainsString('Ann,1,0', $csv);
    }

    public function test_reports_need_both_reports_view_and_the_cut_list_permission(): void
    {
        $role = Role::create(['name' => 'reports-only', 'display_name' => 'Reports only']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'reports.view'], ['display_name' => 'View reports'])->id);
        Sanctum::actingAs(User::factory()->create(['role' => 'reports-only', 'is_active' => true]), ['*']);

        foreach (['productivity', 'recuts', 'waste'] as $path) {
            $this->getJson("/api/v1/reports/cut-station/{$path}")->assertForbidden();
        }

        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'fabrication.work-orders.view'], ['display_name' => 'View work orders'])->id);
        $this->getJson('/api/v1/reports/cut-station/productivity')->assertOk();
    }
}
