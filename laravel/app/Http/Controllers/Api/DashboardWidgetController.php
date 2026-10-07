<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessJob;
use App\Models\CutFlow\CutLogEntry;
use App\Models\CutFlow\Part;
use App\Models\CutFlow\StickSession;
use App\Models\CycleCountSession;
use App\Models\DoorFrameConfiguration;
use App\Models\FabricationDocument;
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
use App\Models\StorageLocation;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Small, cheap aggregate endpoints that power dashboard widgets (see
 * App\Dashboard\WidgetRegistry). Each route must carry the `permission:`
 * middleware matching the widgets that call it.
 */
class DashboardWidgetController extends Controller
{
    /** Open = live (non-archived) work orders that are active or on hold. */
    private function openWorkOrders()
    {
        return FdWorkOrder::where('archived', false)->whereIn('status', ['active', 'on_hold']);
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
            'active_count' => $this->openWorkOrders()->where('status', 'active')->count(),
            'on_hold_count' => $this->openWorkOrders()->where('status', 'on_hold')->count(),
            'overdue_count' => $this->openWorkOrders()->whereNotNull('due_date')->where('due_date', '<', $today)->count(),
            'due_this_week' => $this->openWorkOrders()->whereBetween('due_date', [$today, $weekOut])->count(),
        ]);
    }

    /** Open work orders with the earliest due dates (overdue first), as generic list items. */
    public function workOrdersDue()
    {
        $today = today();

        $items = $this->openWorkOrders()
            ->whereNotNull('due_date')
            ->with('businessJob:id,job_number,job_name')
            ->orderBy('due_date')
            ->limit(8)
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
                    'link' => '/fabrication/work-orders',
                ];
            })->values();

        return response()->json(['items' => $items]);
    }

    /**
     * Open work orders in the work-orders page's order (priority, nulls last),
     * as a generic table: {columns:[{key,label,align?}], rows:[{link, cells}], total}.
     * A cell is a string, or {text, class} to render as a badge.
     */
    public function workOrdersTable()
    {
        $limit = 25;
        $today = today();

        $total = $this->openWorkOrders()->count();
        $workOrders = $this->openWorkOrders()
            ->with('businessJob:id,job_number,job_name')
            ->withCount([
                'elevations',
                'elevations as elevations_complete' => fn ($q) => $q->whereNotNull('date_completed'),
            ])
            ->orderByRaw('priority IS NULL, priority ASC')
            ->orderBy('due_date')
            ->limit($limit)
            ->get();

        $statusLabels = ['active' => 'Active', 'on_hold' => 'On Hold'];

        $rows = $workOrders->map(function (FdWorkOrder $wo) use ($today, $statusLabels) {
            $job = $wo->businessJob;
            $overdue = $wo->due_date && $wo->due_date->lt($today);

            return [
                'link' => '/fabrication/work-orders',
                'cells' => [
                    'release' => $job ? "{$job->job_number}-{$wo->release_token}" : (string) $wo->release_token,
                    'job' => (string) ($job?->job_name ?? ''),
                    'status' => ['text' => $statusLabels[$wo->status] ?? $wo->status, 'class' => $wo->status === 'on_hold' ? 'bg-yellow-lt' : 'bg-green-lt'],
                    'priority' => $wo->priority === null ? '' : (string) $wo->priority,
                    'due' => $wo->due_date
                        ? ['text' => $wo->due_date->format('M j'), 'class' => $overdue ? 'bg-red-lt' : 'bg-secondary-lt']
                        : '',
                    'elevations' => "{$wo->elevations_complete}/{$wo->elevations_count}",
                ],
            ];
        })->values();

        return response()->json([
            'columns' => [
                ['key' => 'release', 'label' => 'Work Order'],
                ['key' => 'job', 'label' => 'Job'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'priority', 'label' => 'Priority', 'align' => 'end'],
                ['key' => 'due', 'label' => 'Due'],
                ['key' => 'elevations', 'label' => 'Elevations', 'align' => 'end'],
            ],
            'rows' => $rows,
            'total' => $total,
        ]);
    }

    /**
     * Open stages (pending / in progress, on live open elevations) grouped by
     * stage name. Stage names come from per-elevation-type templates, so they
     * are returned as data rather than a fixed list.
     */
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
    public function maintenanceUpcoming()
    {
        $items = MaintenanceTask::where('status', 'active')
            ->with('machine:id,name')
            ->get()
            ->filter(fn (MaintenanceTask $t) => $t->is_overdue || $t->is_due_soon)
            ->sortBy(fn (MaintenanceTask $t) => $t->next_due_date)
            ->take(8)
            ->map(function (MaintenanceTask $t) {
                [$meta, $class] = $this->dueMeta(Carbon::parse($t->next_due_date));

                return [
                    'label' => $t->title,
                    'sub' => trim(($t->machine?->name ?? 'Unassigned machine').' · '.ucfirst($t->priority)),
                    'meta' => $meta,
                    'meta_class' => $class,
                    'link' => '/maintenance#tab-tasks',
                ];
            })->values();

        return response()->json(['items' => $items]);
    }

    /** The latest service records. */
    public function maintenanceRecent()
    {
        $items = MaintenanceRecord::with(['machine:id,name', 'task:id,title'])
            ->latest('performed_at')
            ->limit(8)
            ->get()
            ->map(fn (MaintenanceRecord $r) => [
                'label' => $r->machine?->name ?? 'Asset service',
                'sub' => $r->task?->title ?? Str::limit((string) $r->notes, 60),
                'meta' => $r->performed_at?->format('M j'),
                'meta_class' => 'bg-secondary-lt',
                'link' => '/maintenance#tab-records',
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
    public function cycleCountSessions()
    {
        $items = CycleCountSession::active()
            ->with(['items', 'assignedUser:id,name'])
            ->orderBy('scheduled_date')
            ->limit(8)
            ->get()
            ->map(function (CycleCountSession $s) {
                $inProgress = $s->status === 'in_progress';
                $overdue = ! $inProgress && $s->scheduled_date && $s->scheduled_date->lt(today());

                return [
                    'label' => $s->session_number,
                    'sub' => trim(($s->location ?: 'All locations').' · '.$s->progress_percentage.'% counted'.($s->assignedUser ? ' · '.$s->assignedUser->name : '')),
                    'meta' => $inProgress ? 'In progress' : ($s->scheduled_date ? $s->scheduled_date->format('M j') : 'Unscheduled'),
                    'meta_class' => $inProgress ? 'bg-blue-lt' : ($overdue ? 'bg-red-lt' : 'bg-secondary-lt'),
                    'link' => '/cycle-counting',
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
    public function fabricationDocumentsRecent()
    {
        $items = FabricationDocument::latest()->limit(8)->get(['id', 'title', 'type', 'file_name', 'created_at'])
            ->map(fn (FabricationDocument $d) => [
                'label' => $d->title,
                'sub' => ucfirst($d->type).($d->file_name ? ' · '.$d->file_name : ''),
                'meta' => $d->created_at?->format('M j'),
                'meta_class' => 'bg-secondary-lt',
                'link' => '/fabrication/documents',
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
    public function configuratorRecent()
    {
        $badge = ['draft' => 'bg-secondary-lt', 'reserved' => 'bg-blue-lt', 'released' => 'bg-azure-lt', 'in_progress' => 'bg-yellow-lt',
            'completed' => 'bg-green-lt', 'on_hold' => 'bg-orange-lt', 'cancelled' => 'bg-red-lt'];

        $items = DoorFrameConfiguration::notArchived()
            ->with(['businessJob:id,job_number,job_name', 'doors:id,configuration_id,door_tag'])
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(function (DoorFrameConfiguration $c) use ($badge) {
                $tags = $c->doors->pluck('door_tag')->filter()->take(3)->implode(', ');

                return [
                    'label' => trim(($c->businessJob?->job_number ?? 'No job').($tags !== '' ? ' · '.$tags : '')),
                    'sub' => $c->businessJob?->job_name,
                    'meta' => $c->status_label,
                    'meta_class' => $badge[$c->status] ?? 'bg-secondary-lt',
                    'link' => '/config',
                ];
            })->values();

        return response()->json(['items' => $items]);
    }

    /**
     * Storage health. Deliberately not /storage-locations-stats, which loads every location and its
     * inventory rows and sums value in PHP.
     */
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
                'cuts_this_week' => CutLogEntry::where('created_at', '>=', now()->startOfWeek())->count(),
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
    public function purchaseOrdersDue()
    {
        $items = PurchaseOrder::open()
            ->with('supplier:id,name')
            ->orderByRaw('expected_date IS NULL, expected_date ASC')
            ->limit(8)
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
                    'link' => '/purchase-orders',
                ];
            })->values();

        return response()->json(['items' => $items]);
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
    public function jobsDue()
    {
        $items = BusinessJob::whereIn('status', ['active', 'on_hold'])
            ->orderByRaw('target_completion_date IS NULL, target_completion_date ASC')
            ->limit(8)
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
                    'link' => '/jobs',
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
            'today' => InventoryTransaction::where('transaction_date', '>=', today())->count(),
            'this_week' => InventoryTransaction::where('transaction_date', '>=', now()->startOfWeek())->count(),
        ]);
    }

    /** Latest transactions with the signed change in on-hand quantity. Narrow columns: no Product appends. */
    public function transactionsRecent()
    {
        $items = InventoryTransaction::query()
            ->with(['product' => fn ($q) => $q->withTrashed()->select('id', 'sku'), 'user:id,name'])
            ->orderByDesc('transaction_date')->orderByDesc('id')
            ->limit(10)
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
    public function lowStock()
    {
        $rows = Product::where('is_active', true)
            ->where(fn ($q) => $q->where('nonsof', false)->orWhereNull('nonsof'))
            ->excludeMaintenanceConsumables()
            ->whereIn('status', ['critical', 'very_low', 'low'])
            ->orderByRaw("CASE status WHEN 'critical' THEN 0 WHEN 'very_low' THEN 1 ELSE 2 END")
            ->orderBy('quantity_on_hand')
            ->limit(8)
            ->toBase()
            ->get(['id', 'sku', 'description', 'status', 'quantity_on_hand']);

        $style = ['critical' => 'bg-red-lt', 'very_low' => 'bg-orange-lt', 'low' => 'bg-yellow-lt'];

        $items = $rows->map(fn ($p) => [
            'label' => $p->sku,
            'sub' => Str::limit((string) $p->description, 48),
            'meta' => rtrim(rtrim(number_format((float) $p->quantity_on_hand, 1, '.', ''), '0'), '.').' on hand',
            'meta_class' => $style[$p->status] ?? 'bg-secondary-lt',
            'link' => $p->status === 'critical' ? '/critical-stock' : '/low-stock',
        ])->values();

        return response()->json(['items' => $items]);
    }
}
