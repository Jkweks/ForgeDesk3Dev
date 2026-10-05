<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Api\Reports\Concerns\GeneratesCsv;
use App\Http\Controllers\Controller;
use App\Models\BusinessJob;
use Illuminate\Http\Request;

/** Job-level reports: job status and cut material usage per work order. */
class JobReportsController extends Controller
{
    use GeneratesCsv;

    /**
     * Job status summary report.
     * One row per business job with reservation fulfillment, work-order
     * status breakdown, and target-vs-actual completion.
     */
    public function jobStatusSummaryReport(Request $request)
    {
        $statusFilter = $request->get('status');

        $query = BusinessJob::with(['jobReservations.items', 'workOrders']);

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        } else {
            $query->whereIn('status', ['active', 'on_hold']);
        }

        $jobs = $query->orderBy('job_number')->get();

        $rows = $jobs->map(function ($job) {
            $reservations = $job->jobReservations;
            $requested = $reservations->flatMap->items->sum('requested_qty');
            $consumed = $reservations->flatMap->items->sum('consumed_qty');
            $fulfillmentPct = $requested > 0 ? round(($consumed / $requested) * 100, 1) : null;

            $workOrders = $job->workOrders;
            $woByStatus = $workOrders->groupBy('status')->map->count();

            $daysUntilCompletion = $job->days_until_completion;

            return [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'job_name' => $job->job_name,
                'customer_name' => $job->customer_name,
                'status' => $job->status,
                'project_manager' => $job->project_manager,
                'superintendent' => $job->superintendent,
                'start_date' => $job->start_date?->format('Y-m-d'),
                'target_completion_date' => $job->target_completion_date?->format('Y-m-d'),
                'actual_completion_date' => $job->actual_completion_date?->format('Y-m-d'),
                'days_until_completion' => $daysUntilCompletion,
                'is_at_risk' => $daysUntilCompletion !== null && $daysUntilCompletion < 0,
                'reservation_count' => $reservations->count(),
                'open_reservation_count' => $reservations->whereNotIn('status', ['fulfilled', 'cancelled'])->count(),
                'material_fulfillment_pct' => $fulfillmentPct,
                'work_order_count' => $workOrders->count(),
                'work_orders_active' => $woByStatus->get('active', 0),
                'work_orders_on_hold' => $woByStatus->get('on_hold', 0),
                'work_orders_complete' => $woByStatus->get('complete', 0),
            ];
        })->values();

        $fulfillmentValues = $rows->pluck('material_fulfillment_pct')->filter(fn ($v) => $v !== null);

        return response()->json([
            'jobs' => $rows,
            'summary' => [
                'total_jobs' => $rows->count(),
                'active_jobs' => $rows->where('status', 'active')->count(),
                'on_hold_jobs' => $rows->where('status', 'on_hold')->count(),
                'at_risk_jobs' => $rows->where('is_at_risk', true)->count(),
                'avg_material_fulfillment_pct' => $fulfillmentValues->isNotEmpty() ? round($fulfillmentValues->avg(), 1) : null,
            ],
        ]);
    }

    /**
     * Joints completed over time + system breakdown.
     * Joint totals live on fd_wo_elevations (joint_qty), rolled up by
     * completion date and by elevation "system" type (fd_elevation_types).
     */
    /** Stock lengths cut per work order, summarized per job (CutFlow ledger). */
    public function workOrderMaterialUsageReport(Request $request, \App\Services\CutFlow\MaterialUsageReport $report)
    {
        $request->validate([
            'business_job_id' => 'nullable|integer|exists:business_jobs,id',
            'work_order_id' => 'nullable|integer|exists:fd_work_orders,id',
            'q' => 'nullable|string|max:100',
        ]);

        return response()->json($report->build(
            $request->input('q'),
            $request->integer('business_job_id') ?: null,
            $request->integer('work_order_id') ?: null,
        ));
    }

    private function exportJobStatusSummary($request)
    {
        $data = $this->jobStatusSummaryReport($request);
        $items = collect($data->original['jobs']);

        $csvData = $items->map(function ($item) {
            return [
                $item['job_number'],
                $item['job_name'],
                $item['customer_name'] ?? '',
                ucfirst($item['status']),
                $item['project_manager'] ?? '',
                $item['superintendent'] ?? '',
                $item['target_completion_date'] ?? '',
                $item['material_fulfillment_pct'] !== null ? $item['material_fulfillment_pct'].'%' : 'N/A',
                $item['work_order_count'],
                $item['work_orders_active'],
                $item['work_orders_on_hold'],
                $item['work_orders_complete'],
            ];
        });

        return $this->generateCSV($csvData, 'job_status_summary_report', [
            'Job Number', 'Job Name', 'Customer', 'Status', 'Project Manager', 'Superintendent',
            'Target Completion', 'Material Fulfillment', 'Work Orders', 'WO Active', 'WO On Hold', 'WO Complete',
        ]);
    }

    /**
     * Generate PDF for Job Status Summary report
     */
    public function jobStatusSummaryPdf(Request $request)
    {
        $data = $this->jobStatusSummaryReport($request);
        $reportData = $data->original;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.job-status-summary-report', [
            'jobs' => $reportData['jobs'],
            'summary' => $reportData['summary'],
        ]);

        $pdf->setPaper('letter', 'landscape');

        return $pdf->stream('job-status-summary-report-'.date('Y-m-d').'.pdf');
    }

    /** Streams the CSV for one of this controller's export types; null when the type isn't ours. */
    public function csvExport(string $type, Request $request)
    {
        switch ($type) {
            case 'job_status_summary':
                return $this->exportJobStatusSummary($request);
            default:
                return null;
        }
    }
}
