<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdWoElevation;
use App\Models\QualityJointHistory;
use App\Models\QualityReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Chart-feed endpoints for the Quality Reports dashboard. All three group a
 * non-rejected report by an "anchor date" — the matched elevation's
 * date_completed, falling back to the report's own date_issue_discovered
 * when it has no (or no completed) elevation — matching how the rest of the
 * quality-tracking feature keys off elevation completion date.
 */
class QualityAnalyticsController extends Controller
{
    public function incidentRateByMonth(Request $request)
    {
        return response()->json(['data' => $this->buildIncidentRateData(max(1, (int) $request->get('months', 12)))]);
    }

    public function problemTypeRolling13Week()
    {
        [$rows, $window] = $this->buildProblemTypeData();

        return response()->json(['data' => $rows, 'window' => $window]);
    }

    public function weeklyTrend13Week()
    {
        return response()->json(['data' => $this->buildWeeklyTrendData()]);
    }

    /**
     * Renders the three charts (captured client-side as PNG data URLs, since
     * Chart.js draws to a <canvas> dompdf can't render) alongside the same
     * data tables the JSON endpoints above expose, as one downloadable PDF.
     */
    public function exportPdf(Request $request)
    {
        $data = $request->validate([
            'incident_chart' => 'nullable|string',
            'problem_types_chart' => 'nullable|string',
            'weekly_chart' => 'nullable|string',
        ]);

        [$problemTypeRows] = $this->buildProblemTypeData();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.quality-analytics-report', [
            'incidentRows' => $this->buildIncidentRateData(12),
            'problemTypeRows' => $problemTypeRows,
            'weeklyRows' => $this->buildWeeklyTrendData(),
            'incidentChart' => $data['incident_chart'] ?? null,
            'problemTypesChart' => $data['problem_types_chart'] ?? null,
            'weeklyChart' => $data['weekly_chart'] ?? null,
        ]);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('quality-analytics-'.now()->format('Y-m-d').'.pdf');
    }

    private function buildIncidentRateData(int $months): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);
        $end = Carbon::now()->endOfMonth();

        $elevations = FdWoElevation::whereNotNull('date_completed')
            ->whereBetween('date_completed', [$start, $end])
            ->get();
        $jointsByMonth = $elevations->groupBy(fn ($e) => $e->date_completed->format('Y-m'))
            ->map(fn ($group) => (int) $group->sum('joint_qty'));

        // Pre-changeover months have a manual override that replaces (not
        // adds to) whatever partial FdWoElevation data exists for them.
        $jointOverrides = QualityJointHistory::pluck('joint_count', 'month');

        $reports = $this->nonRejectedReportsWithAnchor($start, $end);
        $casesByMonth = $reports->groupBy(fn ($r) => $r->anchor_date->format('Y-m'))
            ->map(fn ($group) => $group->count());

        $result = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $joints = $jointOverrides->get($key) ?? $jointsByMonth->get($key, 0);
            $cases = $casesByMonth->get($key, 0);

            $result[] = [
                'month' => $key,
                'month_label' => $cursor->format('M Y'),
                'joint_count' => $joints,
                'case_count' => $cases,
                'incident_rate' => $joints > 0 ? round((($cases * 10) / $joints) * 100, 2) : null,
            ];
            $cursor->addMonth();
        }

        return $result;
    }

    /** @return array{0: \Illuminate\Support\Collection, 1: array{start: string, end: string}} */
    private function buildProblemTypeData(): array
    {
        $start = Carbon::now()->subWeeks(13)->startOfDay();
        $end = Carbon::now()->endOfDay();

        $reports = $this->nonRejectedReportsWithAnchor($start, $end);

        $rows = $reports->groupBy(fn ($r) => $r->problem_type ?: 'Unspecified')
            ->map(fn ($group, $type) => ['problem_type' => $type, 'count' => $group->count()])
            ->sortByDesc('count')
            ->values();

        return [$rows, ['start' => $start->toDateString(), 'end' => $end->toDateString()]];
    }

    private function buildWeeklyTrendData(): array
    {
        $weeks = [];
        for ($i = 12; $i >= 0; $i--) {
            $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->subWeeks($i);
            $weeks[] = [
                'key' => $weekStart->format('o-W'),
                'week_start' => $weekStart->toDateString(),
                'label' => sprintf('%02d-%02d', $weekStart->isoWeekYear % 100, $weekStart->isoWeek),
            ];
        }

        $start = Carbon::parse($weeks[0]['week_start'])->startOfDay();
        $end = Carbon::now()->endOfDay();

        $reports = $this->nonRejectedReportsWithAnchor($start, $end);
        $casesByWeek = $reports->groupBy(fn ($r) => $r->anchor_date->format('o-W'))
            ->map(fn ($group) => $group->count());

        $counts = array_map(fn ($w) => $casesByWeek->get($w['key'], 0), $weeks);
        $trend = $this->linearTrend($counts);

        $result = [];
        foreach ($weeks as $i => $week) {
            $result[] = [
                'week' => $week['label'],
                'week_start' => $week['week_start'],
                'case_count' => $counts[$i],
                'trend_value' => $trend[$i],
            ];
        }

        return $result;
    }

    private function nonRejectedReportsWithAnchor(Carbon $start, Carbon $end): Collection
    {
        return QualityReport::where('status', '!=', 'rejected')
            ->with('elevation:id,date_completed')
            ->get()
            ->map(function (QualityReport $r) {
                $r->anchor_date = $r->elevation?->date_completed ?? $r->report_date;

                return $r;
            })
            ->filter(fn (QualityReport $r) => $r->anchor_date !== null
                && $r->anchor_date->between($start, $end)
            )
            ->values();
    }

    /** @return array<int, float> fitted y-value at each x=0..n-1 from a least-squares line through the given counts. */
    private function linearTrend(array $counts): array
    {
        $n = count($counts);
        if ($n === 0) {
            return [];
        }

        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumXX = 0;
        foreach ($counts as $x => $y) {
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }

        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        $slope = $denominator != 0 ? (($n * $sumXY) - ($sumX * $sumY)) / $denominator : 0;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        return array_map(fn ($x) => round($intercept + ($slope * $x), 2), array_keys($counts));
    }
}
