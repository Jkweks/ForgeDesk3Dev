<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Dashboard\WidgetRegistry;
use App\Dashboard\WorkOrderColumns;
use App\Models\BusinessJob;
use App\Models\CutFlow\Part;
use App\Models\CutFlow\StickSession;
use App\Models\CycleCountSession;
use App\Models\DoorFrameConfiguration;
use App\Models\FabricationDocument;
use App\Models\FdScheduleEntry;
use App\Models\FdUser;
use App\Models\FdWoStage;
use App\Models\FdWorkOrder;
use App\Models\InventoryLocation;
use App\Models\InventoryTransaction;
use App\Models\JobReservation;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceTask;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\QualityReport;
use App\Models\Shipment;
use App\Models\ShipmentInspection;
use App\Models\StorageLocation;
use App\Services\CutFlow\CutStationStats;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Small, cheap aggregate endpoints that power dashboard widgets (see
 * App\Dashboard\WidgetRegistry). Each route must carry the `permission:`
 * middleware matching the widgets that call it.
 */
class DashboardWidgetController extends Controller
{
    private function limitFor(Request $request, string $widgetKey): int
    {
        return (int) WidgetRegistry::resolveSettings($widgetKey, $request->query())['limit'];
    }

    /** Open = live (non-archived) work orders that are pending, active or on hold. */
    private function openWorkOrders()
    {
        return FdWorkOrder::where('archived', false)->whereIn('status', FdWorkOrder::OPEN_STATUSES);
    }

    /**
     * Counts matching the work-order-backlog report's definitions: overdue is
     * due_date before today; "due this week" is today through 7 days out.
     */
    public function workOrders()
    {
        $today = today()->toDateString();
        $weekOut = today()->addDays(7)->toDateString();

        return response()->json([
            'open' => $this->openWorkOrders()->count(),
            'pending_count' => $this->openWorkOrders()->where('status', 'pending')->count(),
            'active_count' => $this->openWorkOrders()->where('status', 'active')->count(),
            'on_hold_count' => $this->openWorkOrders()->where('status', 'on_hold')->count(),
            'overdue_count' => $this->openWorkOrders()->whereNotNull('due_date')->where('due_date', '<', $today)->count(),
            'due_this_week' => $this->openWorkOrders()->whereBetween('due_date', [$today, $weekOut])->count(),
        ]);
    }

    /** Open work orders with the earliest due dates (overdue first), as generic list items. */
    public function workOrdersDue(Request $request)
    {
        $today = today();

        $items = $this->openWorkOrders()
            ->whereNotNull('due_date')
            ->with('businessJob:id,job_number,job_name')
            ->orderBy('due_date')
            ->limit($this->limitFor($request, 'wo_due_list'))
            ->get()
            ->map(function (FdWorkOrder $wo) use ($today) {
                $days = (int) $today->diffInDays($wo->due_date, false);
                $job = $wo->businessJob;

                return [
                    'label' => $job ? "{$job->job_number}-{$wo->release_token}" : (string) $wo->release_token,
                    'sub' => $job?->job_name,
                    'meta' => match (true) {
                        $days < 0 => abs($days).'d overdue',
                        $days === 0 => 'Due today',
                        default => "Due in {$days}d",
                    },
                    'meta_class' => $days < 0 ? 'bg-red-lt' : ($days <= 7 ? 'bg-yellow-lt' : 'bg-secondary-lt'),
                    'link' => '/fabrication/work-orders?wo='.$wo->id,
                ];
            })->values();

        return response()->json(['items' => $items]);
    }

    /**
     * Open work orders in the work-orders page's order (priority, nulls last),
     * as a generic table: {columns:[{key,label,align?}], rows:[{link, cells}], total}.
     * A cell is a string, or {text, class} to render as a badge.
     */
    public function workOrdersTable(Request $request)
    {
        $settings = WidgetRegistry::resolveSettings('wo_table', $request->query());

        // Mirror the user's Work Orders page columns (order + hidden), unless this widget overrides them.
        $keys = WorkOrderColumns::resolve($request->user(), (string) $settings['columns_mode'], (array) $settings['columns']);

        $query = match ($settings['scope']) {
            'pending' => FdWorkOrder::where('archived', false)->where('status', 'pending'),
            'active' => FdWorkOrder::where('archived', false)->where('status', 'active'),
            'on_hold' => FdWorkOrder::where('archived', false)->where('status', 'on_hold'),
            default => $this->openWorkOrders(),
        };
        $total = (clone $query)->count();

        $query->with(['businessJob:id,job_number,job_name,project_manager', 'assignedUsers'])
            ->withCount([
                'elevations',
                'elevations as elevations_complete' => fn ($q) => $q->whereNotNull('date_completed'),
            ])
            ->withMin('elevations as elevation_due_first', 'date_requested');

        // The labour-estimate columns need elevations/stages/template sets (the heaviest part of the
        // page's own list query), so only load them when one of those columns is showing.
        if (WorkOrderColumns::needsEstimates($keys)) {
            $query->with([
                'elevations:id,work_order_id,joint_qty,template_set_id,date_completed',
                'elevations.stages:id,elevation_id,minutes_per_joint,status',
                'elevations.templateSet:id,minutes_per_joint',
            ]);
        }

        $workOrders = $query
            ->orderByRaw('priority IS NULL, priority ASC')
            ->orderBy('due_date')
            ->limit((int) $settings['limit'])
            ->get();

        $rows = $workOrders->values()->map(fn (FdWorkOrder $wo, int $i) => [
            'link' => '/fabrication/work-orders?wo='.$wo->id,
            'cells' => collect($keys)->mapWithKeys(fn ($k) => [$k => WorkOrderColumns::cell($k, $wo, $i + 1)])->all(),
        ]);

        return response()->json([
            'columns' => WorkOrderColumns::definitions($keys),
            'rows' => $rows,
            'total' => $total,
        ]);
    }

    /**
     * This work week's (Mon-Fri) work queue schedule: one row per active fab user with the same
     * bars as the Work Queue schedule view (planned start -> planned completion in quarter-days,
     * or the due date, for any work order with an open step assigned to them; plus schedule
     * assists and notes). Names only: no steps. The widget lays the bars out client-side.
     */
    public function workQueueWeek()
    {
        $monday = today()->startOfWeek();
        $days = collect(range(0, 4))->map(fn (int $i) => $monday->copy()->addDays($i));
        $from = $days->first()->copy()->startOfDay();
        $to = $days->last()->copy()->startOfDay();

        $users = FdUser::where('active', true)->orderBy('name')->get(['id', 'name']);
        /** @var array<int, array<string, array<string, mixed>>> user => bar key => bar */
        $bars = [];
        $put = function (int $userId, Carbon $start, int $sq, Carbon $end, int $eq, string $text, string $kind) use (&$bars, $from, $to) {
            if ($end->copy()->startOfDay()->lt($from) || $start->copy()->startOfDay()->gt($to)) {
                return;
            }
            $bars[$userId]["$kind|$text|{$start->format('Y-m-d')}|{$end->format('Y-m-d')}"] = [
                'text' => $text, 'kind' => $kind,
                'start' => $start->format('Y-m-d'), 'sq' => $sq, 'end' => $end->format('Y-m-d'), 'eq' => $eq,
            ];
        };
        $jobName = fn (?FdWorkOrder $wo) => (string) ($wo?->businessJob?->job_name ?: $wo?->businessJob?->job_number);

        FdWoStage::query()->actionable()
            ->with(['elevation.workOrder.businessJob:id,job_number,job_name', 'assignees:id'])
            ->get()
            ->each(function (FdWoStage $stage) use ($put, $jobName) {
                $wo = $stage->elevation->workOrder;
                $end = $wo?->planned_completion_date ?? $wo?->due_date ?? $wo?->planned_start_date;
                if (! $end) {
                    return;
                }
                $ids = $stage->assignees->pluck('id');
                if ($ids->isEmpty() && $stage->assigned_to_id) {
                    $ids = collect([$stage->assigned_to_id]);
                }
                foreach ($ids as $uid) {
                    $put((int) $uid, $wo->planned_start_date ?? $end, $wo->planned_start_date ? (int) $wo->planned_start_quarter : 0,
                        $end, $wo->planned_completion_date ? (int) $wo->planned_completion_quarter : 3, $jobName($wo), 'job');
                }
            });

        FdScheduleEntry::with('workOrder.businessJob:id,job_number,job_name')
            ->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)
            ->get()
            ->each(fn (FdScheduleEntry $e) => $put($e->user_id, $e->start_date, (int) $e->start_quarter, $e->end_date, (int) $e->end_quarter,
                $e->work_order_id ? $jobName($e->workOrder) : (string) $e->note, $e->work_order_id ? 'assist' : 'note'));

        return response()->json([
            'days' => $days->map(fn (Carbon $d) => ['key' => $d->format('Y-m-d'), 'label' => $d->format('D j'), 'today' => $d->isToday()])->all(),
            'users' => $users->map(fn (FdUser $u) => ['name' => $u->name, 'bars' => array_values($bars[$u->id] ?? [])])->values(),
            'link' => '/fabrication/work-queue',
        ]);
    }

    /**
     * Open stages (pending / in progress, on live open elevations) grouped by
     * stage name. Stage names come from per-elevation-type templates, so they
     * are returned as data rather than a fixed list.
     */
    /**
     * Programming steps (any stage whose name contains "program") on live work orders: the same set the
     * Programming Queue page lists. Returns the figures the widget's settings ask for, in a fixed order.
     */
    public function programmingQueue(Request $request)
    {
        $settings = WidgetRegistry::resolveSettings('programming_queue', $request->query());
        $order = ['waiting', 'in_progress', 'ready', 'blocked', 'overdue', 'done_week', 'hours'];
        $picked = array_values(array_intersect($order, $settings['metrics'] ?: ['waiting']));
        $unassignedOnly = $settings['scope'] === 'unassigned';

        $base = fn () => FdWoStage::query()
            ->join('fd_wo_elevations as e', 'e.id', '=', 'fd_wo_stages.elevation_id')
            ->join('fd_work_orders as w', 'w.id', '=', 'e.work_order_id')
            ->whereRaw("LOWER(fd_wo_stages.name) LIKE '%program%'")
            ->where('w.archived', false)
            ->when($unassignedOnly, fn ($q) => $q->whereNull('fd_wo_stages.assigned_to_id')
                ->whereDoesntHave('assignees'));
        $open = fn () => $base()->whereIn('fd_wo_stages.status', ['pending', 'in_progress'])->whereNull('e.date_completed');

        $values = [];
        if (array_intersect($picked, ['ready', 'blocked'])) {
            $gate = app(\App\Services\StageGateService::class);
            $stages = $open()->select('fd_wo_stages.*')->with('elevation.stages')->get();
            $blocked = $stages->filter(fn ($s) => $gate->blockingStageForLoaded($s, $s->elevation->stages) !== null
                || $gate->blockingConfigurationReasonFor($s) !== null);
            $values['blocked'] = $blocked->count();
            $values['ready'] = $stages->where('status', 'pending')->reject(fn ($s) => $blocked->contains('id', $s->id))->count();
        }
        $values += [
            'waiting' => $open()->where('fd_wo_stages.status', 'pending')->count(),
            'in_progress' => $open()->where('fd_wo_stages.status', 'in_progress')->count(),
            'overdue' => $open()->whereNotNull('e.date_requested')->where('e.date_requested', '<', today()->toDateString())->count(),
            'done_week' => $base()->where('fd_wo_stages.status', 'complete')->where('fd_wo_stages.completed_at', '>=', now()->startOfWeek())->count(),
            'hours' => round($open()->whereNotNull('fd_wo_stages.minutes_per_joint')->whereNotNull('e.joint_qty')
                ->sum(\Illuminate\Support\Facades\DB::raw('e.joint_qty * fd_wo_stages.minutes_per_joint')) / 60, 1),
        ];
        $labels = [
            'waiting' => 'Waiting', 'in_progress' => 'In progress', 'ready' => 'Ready', 'blocked' => 'Blocked',
            'overdue' => 'Overdue', 'done_week' => 'Done this week', 'hours' => 'Hours left',
        ];

        $figures = array_map(fn ($k) => ['key' => $k, 'label' => $labels[$k], 'total' => $values[$k], 'caption' => $labels[$k]], $picked);

        return response()->json([
            'figures' => $figures,
            // One figure has no column heading, so the caption names it.
            'caption' => count($figures) === 1
                ? $figures[0]['label'].($unassignedOnly ? ' (unassigned)' : '')
                : ($unassignedOnly ? 'Unassigned only' : null),
            'total' => $figures[0]['total'],
        ]);
    }

    public function workOrderStages()
    {
        $rows = FdWoStage::actionable()
            ->reorder()
            ->selectRaw('fd_wo_stages.name as name, COUNT(*) as count')
            ->groupBy('fd_wo_stages.name')
            ->orderByDesc('count')
            ->limit(12)
            ->get();

        return response()->json(['data' => $rows->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->count])->values()]);
    }

    /** Quality reports waiting on a person: pending_review awaits verification, verified awaits review. */
    public function quality()
    {
        $counts = QualityReport::query()
            ->whereIn('status', ['pending_review', 'verified'])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'pending_review' => (int) ($counts['pending_review'] ?? 0),
            'verified' => (int) ($counts['verified'] ?? 0),
        ]);
    }

    /** "Due in 3d" / "2d overdue" / "Due today" plus a badge class, for list rows keyed on a date. */
    private function dueMeta(Carbon $due): array
    {
        $days = (int) today()->diffInDays($due->copy()->startOfDay(), false);

        return [
            match (true) {
                $days < 0 => abs($days).'d overdue',
                $days === 0 => 'Due today',
                default => "Due in {$days}d",
            },
            $days < 0 ? 'bg-red-lt' : ($days <= 7 ? 'bg-yellow-lt' : 'bg-secondary-lt'),
        ];
    }

    /** Active maintenance tasks that are overdue or due soon, earliest first. */
    public function maintenanceUpcoming(Request $request)
    {
        $items = MaintenanceTask::where('status', 'active')
            ->with('machine:id,name')
            ->get()
            ->filter(fn (MaintenanceTask $t) => $t->is_overdue || $t->is_due_soon)
            ->sortBy(fn (MaintenanceTask $t) => $t->next_due_date)
            ->take($this->limitFor($request, 'maintenance_upcoming'))
            ->map(function (MaintenanceTask $t) {
                [$meta, $class] = $this->dueMeta(Carbon::parse($t->next_due_date));

                return [
                    'label' => $t->title,
                    'sub' => trim(($t->machine?->name ?? 'Unassigned machine').' · '.ucfirst($t->priority)),
                    'meta' => $meta,
                    'meta_class' => $class,
                    'link' => '/maintenance?task='.$t->id,
                ];
            })->values();

        return response()->json(['items' => $items]);
    }

    /** The latest service records. */
    public function maintenanceRecent(Request $request)
    {
        $items = MaintenanceRecord::with(['machine:id,name', 'task:id,title'])
            ->latest('performed_at')
            ->limit($this->limitFor($request, 'maintenance_recent'))
            ->get()
            ->map(fn (MaintenanceRecord $r) => [
                'label' => $r->machine?->name ?? 'Asset service',
                'sub' => $r->task?->title ?? Str::limit((string) $r->notes, 60),
                'meta' => $r->performed_at?->format('M j'),
                'meta_class' => 'bg-secondary-lt',
                'link' => '/maintenance?record='.$r->id,
            ])->values();

        return response()->json(['items' => $items]);
    }

    /**
     * Cycle count headline numbers. accuracy_this_month is null when nothing was
     * completed this month (the page-level statistics endpoint reports 100 then,
     * which reads as a real score on a dashboard).
     */
    public function cycleCounts()
    {
        $counts = CycleCountSession::query()
            ->whereIn('status', ['planned', 'in_progress'])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $completed = CycleCountSession::with('items')
            ->where('status', 'completed')
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year)
            ->get();

        return response()->json([
            'planned' => (int) ($counts['planned'] ?? 0),
            'in_progress' => (int) ($counts['in_progress'] ?? 0),
            'active_sessions' => (int) ($counts['planned'] ?? 0) + (int) ($counts['in_progress'] ?? 0),
            'accuracy_this_month' => $completed->isEmpty() ? null : round($completed->avg('accuracy_percentage'), 1),
        ]);
    }

    /** Planned and in-progress cycle count sessions, soonest first. */
    public function cycleCountSessions(Request $request)
    {
        $items = CycleCountSession::active()
            ->with(['items', 'assignedUser:id,name'])
            ->orderBy('scheduled_date')
            ->limit($this->limitFor($request, 'cycle_sessions'))
            ->get()
            ->map(function (CycleCountSession $s) {
                $inProgress = $s->status === 'in_progress';
                $overdue = ! $inProgress && $s->scheduled_date && $s->scheduled_date->lt(today());

                return [
                    'label' => $s->session_number,
                    'sub' => trim(($s->location ?: 'All locations').' · '.$s->progress_percentage.'% counted'.($s->assignedUser ? ' · '.$s->assignedUser->name : '')),
                    'meta' => $inProgress ? 'In progress' : ($s->scheduled_date ? $s->scheduled_date->format('M j') : 'Unscheduled'),
                    'meta_class' => $inProgress ? 'bg-blue-lt' : ($overdue ? 'bg-red-lt' : 'bg-secondary-lt'),
                    'link' => '/cycle-counting?session='.$s->id,
                ];
            })->values();

        return response()->json(['items' => $items]);
    }

    /** Fabrication document counts: total, added in the last 7 days, and by type. */
    public function fabricationDocuments()
    {
        $byType = FabricationDocument::query()->selectRaw('type, COUNT(*) as count')->groupBy('type')->pluck('count', 'type');

        return response()->json([
            'total' => (int) $byType->sum(),
            'added_this_week' => FabricationDocument::where('created_at', '>=', now()->subDays(7))->count(),
            'fabrication' => (int) ($byType['fabrication'] ?? 0),
            'installation' => (int) ($byType['installation'] ?? 0),
            'maintenance' => (int) ($byType['maintenance'] ?? 0),
        ]);
    }

    /** The most recently added fabrication documents. */
    public function fabricationDocumentsRecent(Request $request)
    {
        $items = FabricationDocument::latest()->limit($this->limitFor($request, 'fabdocs_recent'))->get(['id', 'title', 'type', 'file_name', 'created_at'])
            ->map(fn (FabricationDocument $d) => [
                'label' => $d->title,
                'sub' => ucfirst($d->type).($d->file_name ? ' · '.$d->file_name : ''),
                'meta' => $d->created_at?->format('M j'),
                'meta_class' => 'bg-secondary-lt',
                'link' => '/fabrication/documents?doc='.$d->id,
            ])->values();

        return response()->json(['items' => $items]);
    }

    /** Door/frame configurations by status, excluding archived (completed work order) ones. */
    public function configurator()
    {
        $byStatus = DoorFrameConfiguration::notArchived()->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');

        return response()->json([
            'open' => (int) $byStatus->sum(),
            'draft' => (int) ($byStatus['draft'] ?? 0),
            'reserved' => (int) ($byStatus['reserved'] ?? 0),
            'in_progress' => (int) ($byStatus['in_progress'] ?? 0),
            'on_hold' => (int) ($byStatus['on_hold'] ?? 0),
        ]);
    }

    /** Recently updated, non-archived configurations. */
    public function configuratorRecent(Request $request)
    {
        $badge = ['draft' => 'bg-secondary-lt', 'reserved' => 'bg-blue-lt', 'released' => 'bg-azure-lt', 'in_progress' => 'bg-yellow-lt',
            'completed' => 'bg-green-lt', 'on_hold' => 'bg-orange-lt', 'cancelled' => 'bg-red-lt'];

        $items = DoorFrameConfiguration::notArchived()
            ->with(['businessJob:id,job_number,job_name', 'doors:id,configuration_id,door_tag'])
            ->latest('updated_at')
            ->limit($this->limitFor($request, 'configurator_recent'))
            ->get()
            ->map(function (DoorFrameConfiguration $c) use ($badge) {
                $tags = $c->doors->pluck('door_tag')->filter()->take(3)->implode(', ');

                return [
                    'label' => trim(($c->businessJob?->job_number ?? 'No job').($tags !== '' ? ' · '.$tags : '')),
                    'sub' => $c->businessJob?->job_name,
                    'meta' => $c->status_label,
                    'meta_class' => $badge[$c->status] ?? 'bg-secondary-lt',
                    'link' => '/config?config='.$c->id,
                ];
            })->values();

        return response()->json(['items' => $items]);
    }

    /**
     * Storage health. Deliberately not /storage-locations-stats, which loads every location and its
     * inventory rows and sums value in PHP.
     */
    public function productImages()
    {
        return response()->json([
            'missing' => \App\Http\Controllers\Api\Reports\SystemReportsController::missingImagesQuery()->count(),
        ]);
    }

    public function storage()
    {
        return response()->json([
            'locations' => StorageLocation::where('is_active', true)->count(),
            'products_without_location' => Product::where('is_active', true)
                ->whereDoesntHave('inventoryLocations', fn ($q) => $q->whereNotNull('storage_location_id'))
                ->count(),
            'unassigned_stock_rows' => InventoryLocation::whereNull('storage_location_id')->count(),
        ]);
    }

    /**
     * CutFlow numbers live in their own Postgres database. If it is unreachable the
     * dashboard must still load, so failures degrade to nulls (widgets show "-").
     */
    public function cutflow()
    {
        try {
            return response()->json([
                'available' => true,
                'open_jobs' => Part::where('qty_remaining', '>', 0)->distinct()->count('cut_job_id'),
                'active_sticks' => StickSession::where('status', 'active')->whereNull('cancelled_at')->count(),
                'sticks_completed_this_week' => StickSession::where('status', 'complete')->where('updated_at', '>=', now()->startOfWeek())->count(),
                'cuts_this_week' => CutStationStats::totalSince(now()->startOfWeek()),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'available' => false,
                'open_jobs' => null,
                'active_sticks' => null,
                'sticks_completed_this_week' => null,
                'cuts_this_week' => null,
            ]);
        }
    }

    /** Cuts for the chosen period (day / calendar month / calendar year, shop-local), drops and reprints excluded. */
    public function cutflowCuts(Request $request)
    {
        // `periods[]` is the setting; a bare `period` (older saved layouts, API callers) still works.
        $picked = WidgetRegistry::resolveSettings('cutflow_cuts_total', $request->query())['periods'];
        $picked = array_values(array_intersect(array_keys(CutStationStats::PERIODS), $picked ?: ['day']));

        try {
            $rows = array_map(fn ($p) => [
                'period' => $p,
                'label' => CutStationStats::PERIODS[$p],
                'caption' => CutStationStats::periodCaption($p),
                'total' => CutStationStats::totalSince(CutStationStats::periodStart($p)),
            ], $picked);

            return response()->json([
                'available' => true,
                'periods' => $rows,
                // Single-value shape kept for the first period (same fields as before).
                'period' => $rows[0]['period'],
                'caption' => $rows[0]['caption'],
                'total' => $rows[0]['total'],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['available' => false, 'periods' => [], 'period' => $picked[0], 'caption' => null, 'total' => null]);
        }
    }

    // ── Purchase orders ───────────────────────────────────────────────────

    /** "Open" is PurchaseOrder::scopeOpen; "overdue" is open and past expected_date (no model definition exists). */
    public function purchaseOrders()
    {
        $today = today()->toDateString();

        return response()->json([
            'open' => PurchaseOrder::open()->count(),
            'awaiting_approval' => PurchaseOrder::where('status', 'submitted')->count(),
            'overdue' => PurchaseOrder::open()->whereNotNull('expected_date')->where('expected_date', '<', $today)->count(),
            'drafts' => PurchaseOrder::where('status', 'draft')->count(),
        ]);
    }

    /** Open purchase orders, earliest expected date first (undated last). */
    public function purchaseOrdersDue(Request $request)
    {
        $items = PurchaseOrder::open()
            ->with('supplier:id,name')
            ->orderByRaw('expected_date IS NULL, expected_date ASC')
            ->limit($this->limitFor($request, 'po_due_list'))
            ->get(['id', 'po_number', 'supplier_id', 'status', 'expected_date'])
            ->map(function (PurchaseOrder $po) {
                [$meta, $class] = $po->expected_date
                    ? $this->dueMeta($po->expected_date)
                    : ['No date', 'bg-secondary-lt'];

                return [
                    'label' => $po->po_number,
                    'sub' => ($po->supplier?->name ?? 'No supplier').' · '.str_replace('_', ' ', ucfirst($po->status)),
                    'meta' => $meta,
                    'meta_class' => $class,
                    'link' => '/purchase-orders?po='.$po->id,
                ];
            })->values();

        return response()->json(['items' => $items]);
    }

    // ── Shipments ─────────────────────────────────────────────────────────

    /** Not-yet-shipped = draft or pre_check_done; "in transit" = shipped, awaiting delivery. */
    public function shipments()
    {
        $today = today()->toDateString();
        $notShipped = ['draft', 'pre_check_done'];

        return response()->json([
            'open' => Shipment::whereIn('status', Shipment::OPEN_STATUSES)->count(),
            'scheduled_today' => Shipment::whereIn('status', $notShipped)->whereDate('ship_date', $today)->count(),
            'awaiting_pre_check' => Shipment::where('status', 'draft')->count(),
            'in_transit' => Shipment::where('status', 'shipped')->count(),
            'awaiting_verification' => Shipment::where('status', 'awaiting_verification')->count(),
            'overdue' => Shipment::whereIn('status', $notShipped)->whereNotNull('ship_date')->whereDate('ship_date', '<', $today)->count(),
            'delivered_issues_30d' => Shipment::where('status', 'delivered_issues')->where('updated_at', '>=', now()->subDays(30))->count(),
        ]);
    }

    /** Open shipments, soonest ship date first (undated last); shipped ones show as in transit. */
    public function shipmentsUpcoming(Request $request)
    {
        $items = Shipment::whereIn('status', Shipment::OPEN_STATUSES)
            ->with('jobs:business_jobs.id,job_number,job_name')
            ->orderByRaw('ship_date IS NULL, ship_date ASC')
            ->orderBy('shipment_number')
            ->limit($this->limitFor($request, 'shipments_upcoming'))
            ->get()
            ->map(function (Shipment $s) {
                if ($s->status === 'shipped') {
                    [$meta, $class] = ['In transit', 'bg-blue-lt'];
                } elseif ($s->ship_date) {
                    [$meta, $class] = $this->dueMeta($s->ship_date);
                } else {
                    [$meta, $class] = ['No date', 'bg-secondary-lt'];
                }

                return [
                    'label' => $s->shipment_number,
                    'sub' => ($s->jobLabel() ?? 'No job').($s->destination ? ' · '.$s->destination : ''),
                    'meta' => $meta,
                    'meta_class' => $class,
                    'link' => '/shipments/'.$s->id,
                ];
            })->values();

        return response()->json(['items' => $items]);
    }

    /** Shipments delivered with issues, latest first. */
    public function shipmentsIssues(Request $request)
    {
        $items = Shipment::where('status', 'delivered_issues')
            ->with('jobs:business_jobs.id,job_number,job_name')
            ->orderByDesc('updated_at')
            ->limit($this->limitFor($request, 'shipments_issues_list'))
            ->get()
            ->map(fn (Shipment $s) => [
                'label' => $s->shipment_number,
                'sub' => ($s->jobLabel() ?? 'No job').($s->destination ? ' · '.$s->destination : ''),
                'meta' => $s->updated_at->diffForHumans(),
                'meta_class' => 'bg-red-lt',
                'link' => '/shipments/'.$s->id,
            ])->values();

        return response()->json(['items' => $items]);
    }

    /** Shipments sent out per week (Monday-based, by ship date); empty weeks are kept so the axis is continuous. */
    public function shipmentsWeekly(Request $request)
    {
        $weeks = (int) WidgetRegistry::resolveSettings('shipments_weekly', $request->query())['weeks'];
        $start = today()->startOfWeek()->subWeeks($weeks - 1);

        $buckets = [];
        for ($i = 0; $i < $weeks; $i++) {
            $buckets[$start->copy()->addWeeks($i)->toDateString()] = ['shipped' => 0, 'with_issues' => 0];
        }
        Shipment::whereIn('status', ['shipped', 'awaiting_verification', 'delivered', 'delivered_issues'])
            ->whereDate('ship_date', '>=', $start->toDateString())->whereDate('ship_date', '<=', today()->endOfWeek()->toDateString())
            ->get(['status', 'ship_date'])
            ->each(function (Shipment $s) use (&$buckets) {
                $key = $s->ship_date->copy()->startOfWeek()->toDateString();
                if (! isset($buckets[$key])) {
                    return;
                }
                $buckets[$key][$s->status === 'delivered_issues' ? 'with_issues' : 'shipped']++;
            });

        if (collect($buckets)->sum(fn ($b) => $b['shipped'] + $b['with_issues']) === 0) {
            return response()->json(['data' => []]);
        }

        return response()->json(['data' => collect($buckets)->map(fn ($b, $week) => [
            'week' => Carbon::parse($week)->format('M j'),
            'shipped' => $b['shipped'],
            'with_issues' => $b['with_issues'],
        ])->values()]);
    }

    /** Inspection outcomes per calendar month, optionally one phase (pre|post). */
    public function shipmentsInspections(Request $request)
    {
        $settings = WidgetRegistry::resolveSettings('shipments_inspections', $request->query());
        $months = (int) $settings['months'];
        $start = today()->startOfMonth()->subMonths($months - 1);

        $buckets = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i);
            $buckets[$m->format('Y-m')] = ['month_label' => $m->format('M Y'), 'pass' => 0, 'pass_with_issues' => 0, 'fail' => 0];
        }
        $total = 0;
        ShipmentInspection::where('performed_at', '>=', $start)
            ->when($settings['phase'] !== 'all', fn ($q) => $q->where('phase', $settings['phase']))
            ->get(['result', 'performed_at'])
            ->each(function (ShipmentInspection $i) use (&$buckets, &$total) {
                $key = $i->performed_at->format('Y-m');
                $result = $i->result === 'needs_attention' ? 'pass_with_issues' : $i->result;
                if (isset($buckets[$key], $buckets[$key][$result])) {
                    $buckets[$key][$result]++;
                    $total++;
                }
            });

        return response()->json(['data' => $total ? array_values($buckets) : []]);
    }

    // ── Jobs and reservations ─────────────────────────────────────────────

    /** Jobs "past target" are active or on-hold jobs whose target completion date has passed. */
    public function jobs()
    {
        $today = today()->toDateString();

        return response()->json([
            'active' => BusinessJob::where('status', 'active')->count(),
            'on_hold' => BusinessJob::where('status', 'on_hold')->count(),
            'past_target' => BusinessJob::whereIn('status', ['active', 'on_hold'])
                ->whereNotNull('target_completion_date')->where('target_completion_date', '<', $today)->count(),
        ]);
    }

    /** Live jobs ordered by target completion date, overdue first. */
    public function jobsDue(Request $request)
    {
        $items = BusinessJob::whereIn('status', ['active', 'on_hold'])
            ->orderByRaw('target_completion_date IS NULL, target_completion_date ASC')
            ->limit($this->limitFor($request, 'jobs_due_list'))
            ->get(['id', 'job_number', 'job_name', 'status', 'target_completion_date'])
            ->map(function (BusinessJob $j) {
                [$meta, $class] = $j->target_completion_date
                    ? $this->dueMeta($j->target_completion_date)
                    : ['No target', 'bg-secondary-lt'];

                return [
                    'label' => $j->job_number,
                    'sub' => $j->job_name.($j->status === 'on_hold' ? ' · On hold' : ''),
                    'meta' => $meta,
                    'meta_class' => $class,
                    'link' => '/jobs?job='.$j->id,
                ];
            })->values();

        return response()->json(['items' => $items]);
    }

    /** Reservations: open is everything not fulfilled or cancelled; overdue is open and past needed_by. */
    public function reservations()
    {
        $today = today()->toDateString();
        $open = fn () => JobReservation::whereNotIn('status', ['fulfilled', 'cancelled']);

        return response()->json([
            'open' => $open()->count(),
            'in_progress' => JobReservation::where('status', 'in_progress')->count(),
            'on_hold' => JobReservation::where('status', 'on_hold')->count(),
            'overdue' => $open()->whereNotNull('needed_by')->where('needed_by', '<', $today)->count(),
        ]);
    }

    // ── Inventory ─────────────────────────────────────────────────────────

    public function transactions()
    {
        return response()->json([
            'today' => InventoryTransaction::where('transaction_date', '>=', InventoryTransaction::shopPeriodStart('day'))->count(),
            'this_week' => InventoryTransaction::where('transaction_date', '>=', InventoryTransaction::shopPeriodStart('week'))->count(),
        ]);
    }

    /** Latest transactions with the signed change in on-hand quantity. Narrow columns: no Product appends. */
    public function transactionsRecent(Request $request)
    {
        $items = InventoryTransaction::query()
            ->with(['product' => fn ($q) => $q->withTrashed()->select('id', 'sku'), 'user:id,name'])
            ->orderByDesc('transaction_date')->orderByDesc('id')
            ->limit($this->limitFor($request, 'transactions_recent'))
            ->get(['id', 'product_id', 'user_id', 'type', 'quantity_before', 'quantity_after', 'transaction_date'])
            ->map(function (InventoryTransaction $t) {
                $delta = round((float) $t->quantity_after - (float) $t->quantity_before, 1);

                return [
                    'label' => $t->product?->sku ?? 'Unknown product',
                    'sub' => str_replace('_', ' ', ucfirst($t->type)).' · '.($t->user?->name ?? 'System').' · '.$t->transaction_date?->format('M j'),
                    'meta' => ($delta > 0 ? '+' : '').rtrim(rtrim(number_format($delta, 1, '.', ''), '0'), '.'),
                    'meta_class' => $delta > 0 ? 'bg-green-lt' : ($delta < 0 ? 'bg-red-lt' : 'bg-secondary-lt'),
                    'link' => '/transactions',
                ];
            })->values();

        return response()->json(['items' => $items]);
    }

    /** The lowest-stock products (critical first), straight from columns so Product's expensive appends never run. */
    public function lowStock(Request $request)
    {
        $rows = Product::where('is_active', true)
            ->where(fn ($q) => $q->where('nonsof', false)->orWhereNull('nonsof'))
            ->excludeMaintenanceConsumables()
            ->whereIn('status', WidgetRegistry::resolveSettings('low_stock_list', $request->query())['level'] === 'critical' ? ['critical'] : ['critical', 'very_low', 'low'])
            ->orderByRaw("CASE status WHEN 'critical' THEN 0 WHEN 'very_low' THEN 1 ELSE 2 END")
            ->orderBy('quantity_on_hand')
            ->limit($this->limitFor($request, 'low_stock_list'))
            ->toBase()
            ->get(['id', 'sku', 'description', 'status', 'quantity_on_hand']);

        $style = ['critical' => 'bg-red-lt', 'very_low' => 'bg-orange-lt', 'low' => 'bg-yellow-lt'];

        $items = $rows->map(fn ($p) => [
            'label' => $p->sku,
            'sub' => Str::limit((string) $p->description, 48),
            'meta' => rtrim(rtrim(number_format((float) $p->quantity_on_hand, 1, '.', ''), '0'), '.').' on hand',
            'meta_class' => $style[$p->status] ?? 'bg-secondary-lt',
            'link' => ($p->status === 'critical' ? '/critical-stock' : '/low-stock').'?product='.$p->id,
        ])->values();

        return response()->json(['items' => $items]);
    }
}
