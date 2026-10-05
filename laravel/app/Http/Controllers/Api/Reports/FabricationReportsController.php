<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Api\Reports\Concerns\GeneratesCsv;
use App\Http\Controllers\Controller;
use App\Models\FdJobStep;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Shop-floor reports: work order backlog and joints completed. */
class FabricationReportsController extends Controller
{
    use GeneratesCsv;

    /**
     * Work order backlog / queue report.
     * Lists every non-archived, non-complete work order with priority, due
     * dates, assignments, and progress — the exportable version of the
     * shop-floor queue.
     */
    public function workOrderBacklogReport(Request $request)
    {
        $today = Carbon::today();

        $workOrders = FdWorkOrder::where('archived', false)
            ->whereIn('status', ['active', 'on_hold'])
            ->with(['businessJob', 'assignedUsers', 'elevations', 'steps'])
            ->orderBy('priority')
            ->get();

        $rows = $workOrders->map(function ($wo) use ($today) {
            $dueDate = $wo->due_date;
            $daysUntilDue = $dueDate ? $today->diffInDays($dueDate, false) : null;
            $isOverdue = $daysUntilDue !== null && $daysUntilDue < 0;

            $elevations = $wo->elevations;
            $openSteps = $wo->steps->reject(fn ($s) => in_array($s->status, FdJobStep::TERMINAL, true));
            $remaining = $wo->estimateRemainingMinutes();

            return [
                'id' => $wo->id,
                'release_label' => $wo->releaseLabel(),
                'business_job_id' => $wo->business_job_id,
                'job_number' => $wo->businessJob?->job_number,
                'job_name' => $wo->businessJob?->job_name,
                'status' => $wo->status,
                'priority' => $wo->priority,
                'priority_locked' => $wo->priority_locked,
                'date_issued' => $wo->date_issued?->format('Y-m-d'),
                'due_date' => $dueDate?->format('Y-m-d'),
                'days_until_due' => $daysUntilDue,
                'is_overdue' => $isOverdue,
                'planned_start_date' => $wo->planned_start_date?->format('Y-m-d'),
                'planned_completion_date' => $wo->planned_completion_date?->format('Y-m-d'),
                'assigned_users' => $wo->assignedUsers->pluck('name')->values(),
                'elevation_count' => $elevations->count(),
                'elevations_complete_count' => $elevations->whereNotNull('date_completed')->count(),
                'open_steps_count' => $openSteps->count(),
                'estimated_minutes_remaining' => $remaining['effective'],
            ];
        })->values();

        return response()->json([
            'work_orders' => $rows,
            'summary' => [
                'total_open' => $rows->count(),
                'active_count' => $rows->where('status', 'active')->count(),
                'on_hold_count' => $rows->where('status', 'on_hold')->count(),
                'overdue_count' => $rows->where('is_overdue', true)->count(),
                'due_this_week' => $rows->filter(fn ($r) => $r['days_until_due'] !== null && $r['days_until_due'] >= 0 && $r['days_until_due'] <= 7)->count(),
            ],
        ]);
    }

    public function jointsCompletedReport(Request $request)
    {
        $startDate = $request->get('start_date')
            ? Carbon::parse($request->get('start_date'))->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();
        $endDate = $request->get('end_date')
            ? Carbon::parse($request->get('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        $elevations = FdWoElevation::whereNotNull('date_completed')
            ->whereBetween('date_completed', [$startDate, $endDate])
            ->with(['elevationType', 'templateSet', 'workOrder.businessJob'])
            ->get();

        $totalJoints = (int) $elevations->sum('joint_qty');

        $byDate = $elevations->groupBy(fn ($e) => $e->date_completed->format('Y-m-d'))
            ->map(fn ($group, $date) => [
                'date' => $date,
                'joints' => (int) $group->sum('joint_qty'),
                'elevation_count' => $group->count(),
            ])
            ->sortBy('date')
            ->values();

        $bySystem = $elevations->groupBy(fn ($e) => $e->elevationType?->name ?? 'Unclassified')
            ->map(function ($group, $system) use ($totalJoints) {
                $joints = (int) $group->sum('joint_qty');

                return [
                    'system' => $system,
                    'joints' => $joints,
                    'elevation_count' => $group->count(),
                    'job_count' => $group->pluck('workOrder.businessJob.id')->filter()->unique()->count(),
                    'percent_of_total' => $totalJoints > 0 ? round(($joints / $totalJoints) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('joints')
            ->values();

        $byTier = $elevations->groupBy(fn ($e) => $e->templateSet?->name ?? 'No Tier')
            ->map(fn ($group, $tier) => [
                'tier' => $tier,
                'joints' => (int) $group->sum('joint_qty'),
                'elevation_count' => $group->count(),
            ])
            ->sortByDesc('joints')
            ->values();

        return response()->json([
            'by_date' => $byDate,
            'by_system' => $bySystem,
            'by_tier' => $byTier,
            'summary' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'total_joints' => $totalJoints,
                'total_elevations' => $elevations->count(),
                'top_system' => $bySystem->first()['system'] ?? null,
            ],
        ]);
    }

    private function exportWorkOrderBacklog()
    {
        $data = $this->workOrderBacklogReport(request());
        $items = collect($data->original['work_orders']);

        $csvData = $items->map(function ($item) {
            return [
                $item['release_label'],
                $item['job_number'] ?? '',
                $item['job_name'] ?? '',
                ucfirst($item['status']),
                $item['priority'] ?? '',
                $item['due_date'] ?? '',
                $item['days_until_due'] ?? '',
                implode(', ', $item['assigned_users']),
                $item['elevation_count'].'/'.$item['elevations_complete_count'],
                $item['open_steps_count'],
            ];
        });

        return $this->generateCSV($csvData, 'work_order_backlog_report', [
            'Release', 'Job Number', 'Job Name', 'Status', 'Priority', 'Due Date',
            'Days Until Due', 'Assigned To', 'Elevations (Done/Total)', 'Open Steps',
        ]);
    }

    private function exportJointsCompleted($request)
    {
        $data = $this->jointsCompletedReport($request);
        $items = collect($data->original['by_system']);

        $csvData = $items->map(function ($item) {
            return [
                $item['system'],
                $item['joints'],
                $item['elevation_count'],
                $item['job_count'],
                $item['percent_of_total'].'%',
            ];
        });

        return $this->generateCSV($csvData, 'joints_completed_report', [
            'System', 'Joints Completed', 'Elevations', 'Jobs', 'Percent of Total',
        ]);
    }

    /**
     * Generate PDF for Work Order Backlog report
     */
    public function workOrderBacklogPdf(Request $request)
    {
        $data = $this->workOrderBacklogReport($request);
        $reportData = $data->original;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.work-order-backlog-report', [
            'workOrders' => $reportData['work_orders'],
            'summary' => $reportData['summary'],
        ]);

        $pdf->setPaper('letter', 'landscape');

        return $pdf->stream('work-order-backlog-report-'.date('Y-m-d').'.pdf');
    }

    /**
     * Generate PDF for Joints Completed report
     */
    public function jointsCompletedPdf(Request $request)
    {
        $data = $this->jointsCompletedReport($request);
        $reportData = $data->original;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.joints-completed-report', [
            'byDate' => $reportData['by_date'],
            'bySystem' => $reportData['by_system'],
            'byTier' => $reportData['by_tier'],
            'summary' => $reportData['summary'],
        ]);

        $pdf->setPaper('letter', 'landscape');

        return $pdf->stream('joints-completed-report-'.date('Y-m-d').'.pdf');
    }

    /** Streams the CSV for one of this controller's export types; null when the type isn't ours. */
    public function csvExport(string $type, Request $request)
    {
        switch ($type) {
            case 'work_order_backlog':
                return $this->exportWorkOrderBacklog();
            case 'joints_completed':
                return $this->exportJointsCompleted($request);
            default:
                return null;
        }
    }
}
