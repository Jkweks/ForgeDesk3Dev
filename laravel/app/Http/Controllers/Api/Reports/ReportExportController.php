<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** GET /reports/export?type=… — hands the CSV to the controller that owns that report (config/reports.php 'csv'). */
class ReportExportController extends Controller
{
    public function __invoke(Request $request)
    {
        $type = $request->get('type', 'low_stock');

        $report = collect(config('reports.reports'))->firstWhere('csv', $type);
        if (! $report) {
            return response()->json(['message' => 'Invalid report type'], 400);
        }

        return app($report['controller'])->csvExport($type, $request);
    }
}
