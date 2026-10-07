<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CycleCountSession;
use App\Models\FdWoStage;
use App\Models\FdWorkOrder;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceTask;
use App\Models\QualityReport;
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
}
