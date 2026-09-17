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
 * Chart-feed endpoints for the Quality Reports dashboard.
 *
 * The incident-rate-by-month line can be driven by either date_reported (when
 * the issue was discovered) or date_completed (the matched elevation's
 * date_completed, or the manual Pre-Forge date) — per-user choice, saved to
 * users.quality_report_prefs, defaulting to report_date. We're starting on
 * report_date deliberately and plan to flip the default to completed_date
 * once enough data has accumulated to validate that switch; completion dates
 * are still recorded either way so that switch is just a default flip, not a
 * data migration. See resolveIncidentRateBasis().
 *
 * The two rolling-13-week views always use report_date — they drive current
 * improvement focus, not historical production output — regardless of the
 * incident-rate-by-month setting.
 */
class QualityAnalyticsController extends Controller
{
    private const INCIDENT_RATE_BASES = ['report_date', 'completed_date'];

    public function incidentRateByMonth(Request $request)
    {
        $basis = $this->resolveIncidentRateBasis($request);

        return response()->json([
            'data' => $this->buildIncidentRateData(max(1, (int) $request->get('months', 12)), $basis),
            'basis' => $basis,
        ]);
    }

    /** Explicit ?basis= wins (what the on-screen toggle is currently set to); otherwise the user's saved preference; otherwise report_date. */
    private function resolveIncidentRateBasis(Request $request): string
    {
        $requested = $request->get('basis');
        if (in_array($requested, self::INCIDENT_RATE_BASES, true)) {
            return $requested;
        }

        $saved = $request->user()?->quality_report_prefs['incident_rate_basis'] ?? null;

        return in_array($saved, self::INCIDENT_RATE_BASES, true) ? $saved : 'report_date';
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
        $basis = $this->resolveIncidentRateBasis($request);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.quality-analytics-report', [
            'incidentRows' => $this->buildIncidentRateData(12, $basis),
            'incidentBasis' => $basis,
            'problemTypeRows' => $problemTypeRows,
            'weeklyRows' => $this->buildWeeklyTrendData(),
            'incidentChart' => $data['incident_chart'] ?? null,
            'problemTypesChart' => $data['problem_types_chart'] ?? null,
            'weeklyChart' => $data['weekly_chart'] ?? null,
        ]);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('quality-analytics-'.now()->format('Y-m-d').'.pdf');
    }

    private function buildIncidentRateData(int $months, string $basis): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);
        $end = Carbon::now()->endOfMonth();

        // Joints (production volume, the bars) always come from completion
        // date regardless of $basis — only which date buckets a case into a
        // month (the incident-rate line) changes.
        $elevations = FdWoElevation::whereNotNull('date_completed')
            ->whereBetween('date_completed', [$start, $end])
            ->get();
        $jointsByMonth = $elevations->groupBy(fn ($e) => $e->date_completed->format('Y-m'))
            ->map(fn ($group) => (int) $group->sum('joint_qty'));

        // Pre-changeover months have a manual override that replaces (not
        // adds to) whatever partial FdWoElevation data exists for them.
        $jointOverrides = QualityJointHistory::pluck('joint_count', 'month');

        $reports = $this->nonRejectedReportsForIncidentRate($start, $end, $basis);
        $casesByMonth = $reports->groupBy(fn ($r) => $r->anchor_date->format('Y-m'))
            ->map(fn ($group) => $group->count());

        $currentMonthKey = Carbon::now()->format('Y-m');

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
                'projected_incident_rate' => $key === $currentMonthKey
                    ? $this->projectedIncidentRate($cases, $joints)
                    : null,
            ];
            $cursor->addMonth();
        }

        return $result;
    }

    /**
     * Run-rate projection for the in-progress month: extrapolates the sparse,
     * bursty case count forward to a full-month estimate using the elapsed
     * day-of-month fraction, then compares it against joints already logged
     * (not also extrapolated — scaling both by the same factor would just
     * reproduce today's actual rate and tell us nothing new). Suppressed
     * (null) until at least one case has actually been reported this month,
     * since projecting zero forward reads as a misleadingly perfect rate.
     */
    private function projectedIncidentRate(int $casesSoFar, int $jointsSoFar): ?float
    {
        if ($casesSoFar === 0 || $jointsSoFar === 0) {
            return null;
        }

        $now = Carbon::now();
        $projectedCases = $casesSoFar * ($now->daysInMonth / $now->day);

        return round((($projectedCases * 10) / $jointsSoFar) * 100, 2);
    }

    /**
     * Unlike the monthly incident rate (anchored on completion date, to
     * measure quality against production volume), the 13-week views are
     * anchored on report_date — when the issue was actually discovered —
     * since these drive current improvement focus, not historical output.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: array{start: string, end: string}}
     */
    private function buildProblemTypeData(): array
    {
        $start = Carbon::now()->subWeeks(13)->startOfDay();
        $end = Carbon::now()->endOfDay();

        $reports = $this->nonRejectedReportsByReportDate($start, $end);

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

        $reports = $this->nonRejectedReportsByReportDate($start, $end);
        $byWeek = $reports->groupBy(fn ($r) => $r->report_date->format('o-W'));

        $counts = array_map(fn ($w) => $byWeek->get($w['key'], collect())->count(), $weeks);
        $trend = $this->linearTrend($counts);

        // Average completion-to-report lag for that week's cases, in weeks — a
        // read on how far behind discovery is trailing production, shown in
        // the chart tooltip. Null wherever nothing in the bucket has a
        // completion date to measure from (no elevation and no Pre-Forge date).
        $avgLagWeeks = array_map(function ($w) use ($byWeek) {
            $lags = $byWeek->get($w['key'], collect())
                ->map(function (QualityReport $r) {
                    $completed = $r->elevation?->date_completed ?? $r->pre_forge_completed_date;

                    return $completed ? $completed->diffInDays($r->report_date) / 7 : null;
                })
                ->filter(fn ($v) => $v !== null);

            return $lags->isNotEmpty() ? round($lags->avg(), 1) : null;
        }, $weeks);

        $result = [];
        foreach ($weeks as $i => $week) {
            $result[] = [
                'week' => $week['label'],
                'week_start' => $week['week_start'],
                'case_count' => $counts[$i],
                'trend_value' => $trend[$i],
                'avg_lag_weeks' => $avgLagWeeks[$i],
            ];
        }

        return $result;
    }

    /**
     * Used only by the monthly incident rate. $basis picks which date each
     * report is bucketed by: 'completed_date' (elevation's date_completed,
     * or the manual Pre-Forge date) or 'report_date' (when discovered) — each
     * falling back to the other when its own value is missing, so a report
     * still counts somewhere rather than being silently dropped.
     */
    private function nonRejectedReportsForIncidentRate(Carbon $start, Carbon $end, string $basis): Collection
    {
        return QualityReport::where('status', '!=', 'rejected')
            ->with('elevation:id,date_completed')
            ->get()
            ->map(function (QualityReport $r) use ($basis) {
                $completedDate = $r->elevation?->date_completed ?? $r->pre_forge_completed_date;
                $r->anchor_date = $basis === 'completed_date'
                    ? ($completedDate ?? $r->report_date)
                    : ($r->report_date ?? $completedDate);

                return $r;
            })
            ->filter(fn (QualityReport $r) => $r->anchor_date !== null
                && $r->anchor_date->between($start, $end)
            )
            ->values();
    }

    /** Anchored on report_date (when discovered) — used by the 13-week views, which drive current improvement focus rather than historical output. */
    private function nonRejectedReportsByReportDate(Carbon $start, Carbon $end): Collection
    {
        return QualityReport::where('status', '!=', 'rejected')
            ->whereNotNull('report_date')
            ->whereBetween('report_date', [$start, $end])
            ->with('elevation:id,date_completed')
            ->get();
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
