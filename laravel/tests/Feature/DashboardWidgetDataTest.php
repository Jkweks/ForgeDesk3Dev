<?php

namespace Tests\Feature;

use App\Dashboard\WidgetRegistry;
use App\Models\BusinessJob;
use App\Models\CutFlow\CutJob;
use App\Models\CutFlow\Part;
use App\Models\CutFlow\StickSession;
use App\Models\DoorFrameConfiguration;
use App\Models\FabricationDocument;
use App\Models\InventoryTransaction;
use App\Models\JobReservation;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\CycleCountSession;
use App\Models\FdWoElevation;
use App\Models\FdWoStage;
use App\Models\FdWorkOrder;
use App\Models\Machine;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceTask;
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

        // A new work order is derived `pending` while its job steps are open, whatever status it is created with;
        // these fixtures mean a running one unless they say otherwise, so set it after creation.
        $status = $attrs['status'] ?? 'active';
        $wo = FdWorkOrder::create(array_diff_key($attrs, ['status' => 1]) + ['business_job_id' => $job->id, 'release_number' => ++$this->release]);
        FdWorkOrder::whereKey($wo->id)->update(['status' => $status]);

        return $wo->fresh();
    }

    public function test_work_order_counts_follow_backlog_report_definitions(): void
    {
        $this->admin();
        $this->workOrder(['due_date' => today()->subDays(2)]);                       // overdue
        $this->workOrder(['due_date' => today()->addDays(3)]);                       // due this week
        $this->workOrder(['due_date' => today()->addDays(20), 'status' => 'on_hold']); // on hold
        $this->workOrder(['due_date' => today()->subDays(5), 'status' => 'complete']); // closed: ignored
        $this->workOrder(['due_date' => today()->subDays(5), 'archived' => true]);    // archived: ignored
        $this->workOrder(['status' => 'pending']);                                    // waiting on job steps: open, not active

        $this->getJson('/api/v1/dashboard/widgets/work-orders')->assertOk()->assertJson([
            'open' => 4,
            'pending_count' => 1,
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

    public function test_work_order_table_lists_open_orders_by_priority_with_all_page_columns_by_default(): void
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
        // No saved preference: every column, in the Work Orders page's default order.
        $this->assertSame(array_keys(\App\Dashboard\WorkOrderColumns::COLUMNS), array_column($json['columns'], 'key'));
        $this->assertSame(['1', '2', '3'], array_map(fn ($r) => $r['cells']['priority'], $json['rows'])); // null priority falls back to list position
        $this->assertSame('J1-R'.\App\Models\FdWorkOrder::find($late->id)->release_number, $json['rows'][1]['cells']['release']);
        $this->assertSame('bg-orange-lt', $json['rows'][0]['cells']['release']['class']); // on hold
        $this->assertSame('bg-red-lt', $json['rows'][1]['cells']['due']['class']);
        $this->assertSame('1/2 done', $json['rows'][1]['cells']['elevations']);
        $this->assertSame('Pending', $json['rows'][1]['cells']['material']['text']);
        foreach (['work_content', 'est_remaining', 'work_combined'] as $k) {
            $this->assertIsString($json['rows'][1]['cells'][$k], "estimate column {$k} renders without error");
        }
    }

    public function test_work_order_table_mirrors_the_users_saved_column_order_and_hidden_columns(): void
    {
        $user = $this->admin();
        $this->workOrder(['priority' => 1]);

        $user->update(['wo_column_prefs' => ['order' => ['due', 'release', 'priority'], 'hidden' => ['priority', 'pm', 'work_combined']]]);
        $keys = array_column($this->getJson('/api/v1/dashboard/widgets/work-orders/table')->assertOk()->json('columns'), 'key');

        $this->assertSame(['due', 'release'], array_slice($keys, 0, 2));
        $this->assertNotContains('priority', $keys);
        $this->assertNotContains('pm', $keys);
        $this->assertNotContains('work_combined', $keys);
        $this->assertContains('elevations', $keys, 'columns missing from a saved order are appended, as on the page');

        // Legacy preference shape: a bare list of hidden columns, default order.
        $user->update(['wo_column_prefs' => ['elevations', 'assigned']]);
        $keys = array_column($this->getJson('/api/v1/dashboard/widgets/work-orders/table')->json('columns'), 'key');
        $this->assertNotContains('elevations', $keys);
        $this->assertNotContains('assigned', $keys);
        $this->assertSame('priority', $keys[0]);

        // The page saves a change -> the widget follows on its next load, with no widget-side step.
        $user->update(['wo_column_prefs' => ['order' => ['elevations'], 'hidden' => []]]);
        $this->assertSame('elevations', $this->getJson('/api/v1/dashboard/widgets/work-orders/table')->json('columns.0.key'));
    }

    public function test_work_order_table_widget_settings_override_columns_scope_and_limit(): void
    {
        $user = $this->admin();
        $user->update(['wo_column_prefs' => ['order' => ['job_name', 'release', 'due'], 'hidden' => []]]);
        $this->workOrder(['priority' => 1]);
        $this->workOrder(['priority' => 2, 'status' => 'on_hold']);
        $this->workOrder(['priority' => 3]);

        // Custom columns: only the picked ones, still in the user's saved order.
        $custom = $this->getJson('/api/v1/dashboard/widgets/work-orders/table?columns_mode=custom&columns[]=due&columns[]=job_name');
        $this->assertSame(['job_name', 'due'], array_column($custom->assertOk()->json('columns'), 'key'));

        // Custom mode with nothing picked falls back to the user's own columns rather than an empty table.
        $empty = $this->getJson('/api/v1/dashboard/widgets/work-orders/table?columns_mode=custom');
        $this->assertSame('job_name', $empty->json('columns.0.key'));

        $this->assertSame(1, $this->getJson('/api/v1/dashboard/widgets/work-orders/table?scope=on_hold')->json('total'));
        $this->assertSame(2, $this->getJson('/api/v1/dashboard/widgets/work-orders/table?scope=active')->json('total'));

        $limited = $this->getJson('/api/v1/dashboard/widgets/work-orders/table?limit=10')->json();
        $this->assertCount(3, $limited['rows']);
        // Out-of-range values fall back to the default instead of erroring.
        $this->getJson('/api/v1/dashboard/widgets/work-orders/table?limit=9999&scope=bogus&columns_mode=nope')->assertOk()->assertJsonPath('total', 3);
    }

    public function test_list_widgets_honor_the_rows_setting_and_low_stock_level(): void
    {
        $this->admin();
        foreach (range(1, 10) as $i) {
            $this->product("LL-{$i}", ['status' => $i === 1 ? 'critical' : 'low', 'quantity_on_hand' => $i]);
        }

        $this->assertCount(8, $this->getJson('/api/v1/dashboard/widgets/low-stock')->json('items'));          // default 8
        $this->assertCount(5, $this->getJson('/api/v1/dashboard/widgets/low-stock?limit=5')->json('items'));
        $this->assertCount(8, $this->getJson('/api/v1/dashboard/widgets/low-stock?limit=7777')->json('items')); // invalid -> default
        $this->assertCount(1, $this->getJson('/api/v1/dashboard/widgets/low-stock?level=critical')->json('items'));
    }

    public function test_layout_save_sanitizes_widget_settings_against_the_schema(): void
    {
        $this->admin();
        $widget = ['id' => 'w1', 'key' => 'wo_table', 'x' => 0, 'y' => 0, 'w' => 8, 'h' => 6, 'settings' => [
            'limit' => '25', 'scope' => 'active', 'columns_mode' => 'custom', 'columns' => ['due', 'bogus', 'release'],
            'evil' => 'x', 'columns_extra' => 1,
        ]];
        $bad = ['id' => 'w2', 'key' => 'inventory_skus', 'x' => 0, 'y' => 6, 'w' => 3, 'h' => 2, 'settings' => ['limit' => 5]];

        $saved = $this->putJson('/api/v1/dashboard/layout', ['widgets' => [$widget, $bad]])->assertOk()->json('layout.widgets');

        $this->assertEquals(['limit' => 25, 'scope' => 'active', 'columns_mode' => 'custom', 'columns' => ['due', 'release']], $saved[0]['settings']);
        $this->assertSame([], $saved[1]['settings'], 'a widget with no schema keeps no settings');
        $this->assertTrue(collect($this->getJson('/api/v1/dashboard/widgets')->json('widgets'))->firstWhere('key', 'wo_table')['settings_schema'] !== []);
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

    public function test_maintenance_upcoming_lists_overdue_then_due_soon_and_skips_the_rest(): void
    {
        $this->admin();
        $machine = Machine::create(['name' => 'Saw 1', 'equipment_type' => 'machine']);
        $task = fn (string $title, int $startedDaysAgo, array $extra = []) => MaintenanceTask::create($extra + [
            'machine_id' => $machine->id, 'title' => $title, 'status' => 'active', 'priority' => 'high',
            'start_date' => today()->subDays($startedDaysAgo), 'interval_count' => 30, 'interval_unit' => 'day',
        ]);
        $task('Blade change', 40);                       // due 10 days ago
        $task('Lubricate rails', 25);                    // due in 5 days
        $task('Annual service', 1);                      // due in 29 days: not due soon
        $task('Paused task', 40, ['status' => 'paused']); // ignored

        $items = $this->getJson('/api/v1/dashboard/widgets/maintenance/upcoming')->assertOk()->json('items');

        $this->assertSame(['Blade change', 'Lubricate rails'], array_column($items, 'label'));
        $this->assertSame('10d overdue', $items[0]['meta']);
        $this->assertSame('bg-red-lt', $items[0]['meta_class']);
        $this->assertSame('Due in 5d', $items[1]['meta']);
        $this->assertStringStartsWith('Saw 1', $items[0]['sub']);

        // The existing gated counts endpoint that the stat widgets read agrees.
        $this->getJson('/api/v1/maintenance/dashboard')->assertOk()->assertJson(['overdue_task_count' => 1, 'due_soon_task_count' => 1]);
    }

    public function test_maintenance_recent_lists_latest_service_records_first(): void
    {
        $this->admin();
        $machine = Machine::create(['name' => 'Router 2', 'equipment_type' => 'machine']);
        MaintenanceRecord::create(['machine_id' => $machine->id, 'performed_at' => today()->subDays(5), 'notes' => 'Older']);
        MaintenanceRecord::create(['machine_id' => $machine->id, 'performed_at' => today()->subDay(), 'notes' => 'Replaced belt']);

        $items = $this->getJson('/api/v1/dashboard/widgets/maintenance/recent')->assertOk()->json('items');

        $this->assertCount(2, $items);
        $this->assertSame('Router 2', $items[0]['label']);
        $this->assertSame('Replaced belt', $items[0]['sub']);
        $this->assertSame(today()->subDay()->format('M j'), $items[0]['meta']);
    }

    public function test_cycle_count_numbers_and_sessions(): void
    {
        $this->admin();
        $before = $this->getJson('/api/v1/dashboard/widgets/cycle-counts')->assertOk()->json();
        $this->assertNull($before['accuracy_this_month'], 'no completed sessions this month => no score, not a fake 100');

        CycleCountSession::create(['session_number' => 'CC-1', 'status' => 'planned', 'scheduled_date' => today()->subDays(2)]);
        CycleCountSession::create(['session_number' => 'CC-2', 'status' => 'in_progress', 'scheduled_date' => today()]);
        CycleCountSession::create(['session_number' => 'CC-3', 'status' => 'planned', 'scheduled_date' => today()->addDays(3)]);
        CycleCountSession::create(['session_number' => 'CC-4', 'status' => 'completed', 'scheduled_date' => today(), 'completed_at' => now()]);
        CycleCountSession::create(['session_number' => 'CC-5', 'status' => 'cancelled', 'scheduled_date' => today()]);

        $this->getJson('/api/v1/dashboard/widgets/cycle-counts')->assertOk()->assertJson([
            'planned' => $before['planned'] + 2,
            'in_progress' => $before['in_progress'] + 1,
            'active_sessions' => $before['active_sessions'] + 3,
        ])->assertJsonPath('accuracy_this_month', 100);

        $items = $this->getJson('/api/v1/dashboard/widgets/cycle-counts/sessions')->assertOk()->json('items');
        $byLabel = collect($items)->keyBy('label');
        $this->assertSame('bg-red-lt', $byLabel['CC-1']['meta_class']);   // planned and past its date
        $this->assertSame('In progress', $byLabel['CC-2']['meta']);
        $this->assertSame('bg-secondary-lt', $byLabel['CC-3']['meta_class']);
        $this->assertArrayNotHasKey('CC-4', $byLabel->all());
        $this->assertArrayNotHasKey('CC-5', $byLabel->all());
    }

    private function product(string $sku, array $state = []): Product
    {
        $supplier = Supplier::firstOrCreate(['name' => 'Test Supplier']);
        $product = Product::create(['sku' => $sku, 'description' => "Desc {$sku}", 'supplier_id' => $supplier->id]);
        // Query update on purpose: model saves recompute status/quantities.
        $state && Product::where('id', $product->id)->update($state);

        return $product->fresh();
    }

    public function test_purchase_order_counts_and_due_list(): void
    {
        $this->admin();
        $supplier = Supplier::create(['name' => 'Acme']);
        $po = fn (string $n, string $status, $expected) => PurchaseOrder::create([
            'po_number' => $n, 'order_date' => today(), 'status' => $status, 'expected_date' => $expected, 'supplier_id' => $supplier->id,
        ]);
        $po('PO-T-1', 'approved', today()->subDays(3));   // open, overdue
        $po('PO-T-2', 'submitted', today()->addDays(2));  // open, awaiting approval
        $po('PO-T-3', 'partially_received', null);        // open, undated
        $po('PO-T-4', 'draft', today()->subDays(9));      // not open
        $po('PO-T-5', 'received', today()->subDays(9));   // not open

        $this->getJson('/api/v1/dashboard/widgets/purchase-orders')->assertOk()->assertJson([
            'open' => 3, 'awaiting_approval' => 1, 'overdue' => 1, 'drafts' => 1,
        ]);

        $items = $this->getJson('/api/v1/dashboard/widgets/purchase-orders/due')->assertOk()->json('items');
        $this->assertSame(['PO-T-1', 'PO-T-2', 'PO-T-3'], array_column($items, 'label'));
        $this->assertSame('3d overdue', $items[0]['meta']);
        $this->assertSame('bg-red-lt', $items[0]['meta_class']);
        $this->assertSame('No date', $items[2]['meta']);
        $this->assertStringStartsWith('Acme', $items[0]['sub']);
    }

    public function test_jobs_and_reservations_counts(): void
    {
        $this->admin();
        $job = fn (string $n, string $status, $target) => BusinessJob::create(['job_number' => $n, 'job_name' => "Job {$n}", 'status' => $status, 'target_completion_date' => $target]);
        $job('JT-1', 'active', today()->subDays(4));    // past target
        $job('JT-2', 'active', today()->addDays(10));
        $job('JT-3', 'on_hold', today()->subDay());     // past target
        $job('JT-4', 'completed', today()->subDays(30)); // ignored
        $before = $this->getJson('/api/v1/dashboard/widgets/jobs')->json();

        $this->assertSame(2, $before['active']);
        $this->assertSame(1, $before['on_hold']);
        $this->assertSame(2, $before['past_target']);

        $items = $this->getJson('/api/v1/dashboard/widgets/jobs/due')->assertOk()->json('items');
        $this->assertSame(['JT-1', 'JT-3', 'JT-2'], array_column($items, 'label'));
        $this->assertSame('4d overdue', $items[0]['meta']);
        $this->assertStringContainsString('On hold', $items[1]['sub']);

        $linked = BusinessJob::where('job_number', 'JT-2')->first();
        $res = fn (string $n, string $status, $needed) => JobReservation::create([
            'job_number' => $n, 'job_name' => "Res {$n}", 'requested_by' => 'Tester', 'status' => $status, 'needed_by' => $needed,
            'business_job_id' => $linked->id, // reservation_id is only generated for job-linked reservations
        ]);
        $res('RT-1', 'active', today()->subDays(2));       // open, overdue
        $res('RT-2', 'in_progress', today()->addDays(5));  // open
        $res('RT-3', 'on_hold', null);                     // open
        $res('RT-4', 'fulfilled', today()->subDays(9));    // closed
        $res('RT-5', 'cancelled', today()->subDays(9));    // closed

        $this->getJson('/api/v1/dashboard/widgets/reservations')->assertOk()->assertJson([
            'open' => 3, 'in_progress' => 1, 'on_hold' => 1, 'overdue' => 1,
        ]);
    }

    public function test_transactions_recent_shows_signed_change_and_counts_today(): void
    {
        $this->admin();
        $p = $this->product('TX-1');
        $tx = fn (string $type, float $before, float $after, $when) => InventoryTransaction::create([
            'product_id' => $p->id, 'type' => $type, 'quantity' => abs($after - $before),
            'quantity_before' => $before, 'quantity_after' => $after, 'transaction_date' => $when,
        ]);
        $tx('receipt', 0, 10, now());
        $tx('issue', 10, 7.5, now()->subMinute());
        $tx('adjustment', 7.5, 7.5, now()->subDays(3));

        $this->getJson('/api/v1/dashboard/widgets/transactions')->assertOk()->assertJson(['today' => 2]);

        $items = $this->getJson('/api/v1/dashboard/widgets/transactions/recent')->assertOk()->json('items');
        $this->assertSame(['+10', '-2.5', '0'], array_column($items, 'meta'));
        $this->assertSame(['bg-green-lt', 'bg-red-lt', 'bg-secondary-lt'], array_column($items, 'meta_class'));
        $this->assertSame('TX-1', $items[0]['label']);
        $this->assertStringContainsString('System', $items[0]['sub']);
    }

    public function test_low_stock_list_orders_critical_first_and_skips_healthy_items(): void
    {
        $this->admin();
        $this->product('LS-OK', ['status' => 'in_stock', 'quantity_on_hand' => 500]);
        $this->product('LS-LOW', ['status' => 'low', 'quantity_on_hand' => 12]);
        $this->product('LS-CRIT', ['status' => 'critical', 'quantity_on_hand' => 0]);
        $this->product('LS-VLOW', ['status' => 'very_low', 'quantity_on_hand' => 3]);
        $this->product('LS-INACTIVE', ['status' => 'critical', 'quantity_on_hand' => 0, 'is_active' => false]);

        $items = $this->getJson('/api/v1/dashboard/widgets/low-stock')->assertOk()->json('items');

        $this->assertSame(['LS-CRIT', 'LS-VLOW', 'LS-LOW'], array_column($items, 'label'));
        $this->assertSame('bg-red-lt', $items[0]['meta_class']);
        $this->assertSame('/critical-stock', $items[0]['link']);
        $this->assertSame('3 on hand', $items[1]['meta']);
    }

    public function test_fabrication_documents_counts_and_recent(): void
    {
        $this->admin();
        FabricationDocument::create(['title' => 'Old drawing', 'type' => 'fabrication'])->forceFill(['created_at' => now()->subDays(20)])->save();
        FabricationDocument::create(['title' => 'Install guide', 'type' => 'installation', 'file_name' => 'guide.pdf']);
        FabricationDocument::create(['title' => 'PM checklist', 'type' => 'maintenance']);

        $this->getJson('/api/v1/dashboard/widgets/fabrication-documents')->assertOk()->assertJson([
            'total' => 3, 'added_this_week' => 2, 'fabrication' => 1, 'installation' => 1, 'maintenance' => 1,
        ]);

        $items = $this->getJson('/api/v1/dashboard/widgets/fabrication-documents/recent')->assertOk()->json('items');
        $this->assertCount(3, $items);
        $this->assertSame('Old drawing', $items[2]['label']);
        $this->assertSame('Installation · guide.pdf', collect($items)->firstWhere('label', 'Install guide')['sub']);
    }

    public function test_configurator_counts_exclude_archived_and_recent_lists_status(): void
    {
        $this->admin();
        $job = BusinessJob::create(['job_number' => 'CF-1', 'job_name' => 'Config Job', 'status' => 'active']);
        $cfg = fn (string $status, bool $archived = false) => DoorFrameConfiguration::create([
            'business_job_id' => $job->id, 'job_scope' => 'door_and_frame', 'status' => $status, 'archived' => $archived,
        ]);
        $cfg('draft'); $cfg('draft'); $cfg('on_hold'); $cfg('in_progress'); $cfg('completed', true);

        $this->getJson('/api/v1/dashboard/widgets/configurator')->assertOk()->assertJson([
            'open' => 4, 'draft' => 2, 'on_hold' => 1, 'in_progress' => 1, 'reserved' => 0,
        ]);

        $items = $this->getJson('/api/v1/dashboard/widgets/configurator/recent')->assertOk()->json('items');
        $this->assertCount(4, $items);
        $this->assertStringStartsWith('CF-1', $items[0]['label']);
        $this->assertSame('Config Job', $items[0]['sub']);
    }

    public function test_storage_counts_active_locations_and_unassigned_products(): void
    {
        $this->admin();
        // Migrations seed some rows into the test DB, so assert on the change.
        $before = $this->getJson('/api/v1/dashboard/widgets/storage')->assertOk()->json();

        StorageLocation::create(['name' => 'A1']);
        StorageLocation::create(['name' => 'Old', 'is_active' => false]);
        $this->product('ST-1');
        $this->product('ST-2', ['is_active' => false]);

        $after = $this->getJson('/api/v1/dashboard/widgets/storage')->assertOk()->json();

        $this->assertSame($before['locations'] + 1, $after['locations']);
        $this->assertSame($before['products_without_location'] + 1, $after['products_without_location']);
    }

    public function test_cutflow_widget_degrades_instead_of_erroring_when_its_database_is_unavailable(): void
    {
        $this->admin();
        // RefreshDatabase does not create the separate cutflow connection's tables, which stands in for
        // "cutflow Postgres is down": the dashboard must still get a 200 with nulls.
        $this->getJson('/api/v1/dashboard/widgets/cutflow')->assertOk()->assertJson([
            'available' => false, 'open_jobs' => null, 'active_sticks' => null, 'cuts_this_week' => null,
        ]);
    }

    public function test_cutflow_counts_open_jobs_and_sticks(): void
    {
        $this->admin();
        $this->artisan('migrate', ['--database' => 'cutflow', '--path' => 'database/migrations/cutflow', '--force' => true]);

        $job = CutJob::create(['name' => 'Cut Job']);
        $done = CutJob::create(['name' => 'Done Job']);
        Part::create(['cut_job_id' => $job->id, 'name' => 'Rail', 'dimension_inches' => 48, 'qty_original' => 4, 'qty_remaining' => 2]);
        Part::create(['cut_job_id' => $done->id, 'name' => 'Jamb', 'dimension_inches' => 80, 'qty_original' => 2, 'qty_remaining' => 0]);
        StickSession::create(['length_label' => '20ft', 'part_name' => 'Rail', 'length_inches' => 240, 'status' => 'active']);
        StickSession::create(['length_label' => '20ft', 'part_name' => 'Rail', 'length_inches' => 240, 'status' => 'complete']);

        $this->getJson('/api/v1/dashboard/widgets/cutflow')->assertOk()->assertJson([
            'available' => true, 'open_jobs' => 1, 'active_sticks' => 1, 'sticks_completed_this_week' => 1,
        ]);
    }

    public function test_widget_data_endpoints_are_permission_gated(): void
    {
        $this->noPermissions();

        foreach (['work-orders', 'work-orders/due', 'work-orders/table', 'work-orders/stages', 'quality', 'maintenance/upcoming', 'maintenance/recent', 'cycle-counts', 'cycle-counts/sessions', 'purchase-orders', 'purchase-orders/due', 'jobs', 'jobs/due', 'reservations', 'transactions', 'transactions/recent', 'low-stock', 'fabrication-documents', 'fabrication-documents/recent', 'configurator', 'configurator/recent', 'storage', 'cutflow'] as $path) {
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
