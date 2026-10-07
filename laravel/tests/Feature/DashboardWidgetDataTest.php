<?php

namespace Tests\Feature;

use App\Dashboard\WidgetRegistry;
use App\Models\BusinessJob;
use App\Models\FdWoElevation;
use App\Models\FdWoStage;
use App\Models\FdWorkOrder;
use App\Models\QualityReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardWidgetDataTest extends TestCase
{
    use RefreshDatabase;

    private int $release = 0;

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function noPermissions(): void
    {
        $user = User::factory()->create(['role' => 'no-such-role', 'is_active' => true]);
        Sanctum::actingAs($user, ['*']);
    }

    private function workOrder(array $attrs = []): FdWorkOrder
    {
        $job = BusinessJob::firstOrCreate(['job_number' => 'J1'], ['job_name' => 'Job One', 'status' => 'active']);

        return FdWorkOrder::create($attrs + ['business_job_id' => $job->id, 'release_number' => ++$this->release]);
    }

    public function test_work_order_counts_follow_backlog_report_definitions(): void
    {
        $this->admin();
        $this->workOrder(['due_date' => today()->subDays(2)]);                       // overdue
        $this->workOrder(['due_date' => today()->addDays(3)]);                       // due this week
        $this->workOrder(['due_date' => today()->addDays(20), 'status' => 'on_hold']); // on hold
        $this->workOrder(['due_date' => today()->subDays(5), 'status' => 'complete']); // closed: ignored
        $this->workOrder(['due_date' => today()->subDays(5), 'archived' => true]);    // archived: ignored

        $this->getJson('/api/v1/dashboard/widgets/work-orders')->assertOk()->assertJson([
            'open' => 3,
            'active_count' => 2,
            'on_hold_count' => 1,
            'overdue_count' => 1,
            'due_this_week' => 1,
        ]);
    }

    public function test_due_list_orders_overdue_first_and_labels_them(): void
    {
        $this->admin();
        $this->workOrder(['due_date' => today()->addDays(4)]);
        $this->workOrder(['due_date' => today()->subDays(2)]);
        $this->workOrder(['due_date' => null]);

        $items = $this->getJson('/api/v1/dashboard/widgets/work-orders/due')->assertOk()->json('items');

        $this->assertCount(2, $items);
        $this->assertSame('2d overdue', $items[0]['meta']);
        $this->assertSame('bg-red-lt', $items[0]['meta_class']);
        $this->assertSame('Due in 4d', $items[1]['meta']);
        $this->assertStringStartsWith('J1-', $items[0]['label']);
    }

    public function test_work_order_table_lists_open_orders_by_priority(): void
    {
        $this->admin();
        $late = $this->workOrder(['due_date' => today()->subDay(), 'priority' => 2]);
        $this->workOrder(['due_date' => today()->addDays(9), 'priority' => 1, 'status' => 'on_hold']);
        $this->workOrder(['priority' => null]);
        $this->workOrder(['status' => 'complete', 'priority' => 0]);
        FdWoElevation::create(['work_order_id' => $late->id, 'elevation_tag' => 'E1', 'date_completed' => today()]);
        FdWoElevation::create(['work_order_id' => $late->id, 'elevation_tag' => 'E2']);

        $json = $this->getJson('/api/v1/dashboard/widgets/work-orders/table')->assertOk()->json();

        $this->assertSame(3, $json['total']);
        $this->assertSame(['1', '2', ''], array_map(fn ($r) => $r['cells']['priority'], $json['rows']));
        $this->assertSame('On Hold', $json['rows'][0]['cells']['status']['text']);
        $this->assertSame('bg-red-lt', $json['rows'][1]['cells']['due']['class']);
        $this->assertSame('1/2', $json['rows'][1]['cells']['elevations']);
        $this->assertSame(['release', 'job', 'status', 'priority', 'due', 'elevations'], array_column($json['columns'], 'key'));
    }

    public function test_stage_wip_groups_open_stages_by_name(): void
    {
        $this->admin();
        $wo = $this->workOrder();
        $open = FdWoElevation::create(['work_order_id' => $wo->id, 'elevation_tag' => 'E1']);
        $done = FdWoElevation::create(['work_order_id' => $wo->id, 'elevation_tag' => 'E2', 'date_completed' => today()]);

        foreach ([['Frame Fab', 'pending'], ['Frame Fab', 'in_progress'], ['Glazing Prep', 'pending'], ['Frame Fab', 'complete']] as $i => [$name, $status]) {
            FdWoStage::create(['work_order_id' => $wo->id, 'elevation_id' => $open->id, 'name' => $name, 'sort_order' => $i, 'status' => $status]);
        }
        FdWoStage::create(['work_order_id' => $wo->id, 'elevation_id' => $done->id, 'name' => 'Frame Fab', 'sort_order' => 9, 'status' => 'pending']);

        $data = $this->getJson('/api/v1/dashboard/widgets/work-orders/stages')->assertOk()->json('data');

        $this->assertSame([['name' => 'Frame Fab', 'count' => 2], ['name' => 'Glazing Prep', 'count' => 1]], $data);
    }

    public function test_quality_counts_pending_and_verified(): void
    {
        $this->admin();
        // Migrations seed historical reports, so assert on the change, not absolutes.
        $before = $this->getJson('/api/v1/dashboard/widgets/quality')->assertOk()->json();

        QualityReport::create(['status' => 'pending_review']);
        QualityReport::create(['status' => 'pending_review']);
        QualityReport::create(['status' => 'verified']);
        QualityReport::create(['status' => 'reviewed']);

        $this->getJson('/api/v1/dashboard/widgets/quality')->assertOk()->assertJson([
            'pending_review' => $before['pending_review'] + 2,
            'verified' => $before['verified'] + 1,
        ]);
    }

    public function test_widget_data_endpoints_are_permission_gated(): void
    {
        $this->noPermissions();

        foreach (['work-orders', 'work-orders/due', 'work-orders/table', 'work-orders/stages', 'quality'] as $path) {
            $this->getJson("/api/v1/dashboard/widgets/{$path}")->assertForbidden();
        }
    }

    public function test_every_widget_endpoint_is_gated_by_its_declared_permission(): void
    {
        $this->noPermissions();

        foreach (WidgetRegistry::all() as $widget) {
            $this->getJson('/api/v1'.$widget['endpoint'])
                ->assertForbidden("{$widget['key']} endpoint {$widget['endpoint']} is reachable without {$widget['permission'][0]}");
        }
    }
}
