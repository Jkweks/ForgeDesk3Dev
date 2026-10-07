<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdWoStage;
use App\Models\FdWorkOrder;
use App\Models\QualityReport;

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
}
