<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Api\Reports\Concerns\GeneratesCsv;
use App\Http\Controllers\Controller;
use App\Services\CutFlow\CutStationStats as Stats;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;

/**
 * Cut station reports (productivity, recuts, stock and waste), built on CutStationStats so they
 * agree with the dashboard widget on what counts as a cut. These carry per-operator numbers, so
 * on top of reports.view they need the same permission as the cut lists.
 *
 * Recuts are attributed to whoever logged the recut, not whoever made the bad cut: the log does
 * not link a recut to the entry it replaces.
 */
class CutStationReportsController extends Controller implements HasMiddleware
{
    use GeneratesCsv;

    public static function middleware(): array
    {
        return [new Middleware('permission:fabrication.work-orders.view')];
    }

    /** Cuts per operator, per day and per hour of day. */
    public function productivityReport(Request $request)
    {
        return response()->json($this->productivity($request));
    }

    /** Recut rate per job, per operator and per profile. */
    public function recutReport(Request $request)
    {
        return response()->json($this->recuts($request));
    }

    /** Sticks cut, leftover, rack drops and scrap per profile. */
    public function wasteReport(Request $request)
    {
        return response()->json($this->waste($request));
    }

    public function csvExport(string $type, Request $request)
    {
        switch ($type) {
            case 'cut_station_productivity':
                $report = $this->productivity($request);

                return $this->generateCSV(
                    collect($report['by_operator'])->map(fn ($r) => [
                        $r['operator'], $r['cuts'], $r['recuts'], $r['recut_rate'], $r['manual_cuts'],
                        $r['inches'], $r['active_days'], $r['avg_per_active_day'], $r['first_cut'], $r['last_cut'],
                    ]),
                    'cut_station_productivity',
                    ['Operator', 'Cuts', 'Recuts', 'Recut Rate %', 'Manual Cuts', 'Inches Cut', 'Active Days', 'Avg Cuts / Active Day', 'First Cut', 'Last Cut']
                );
            case 'cut_station_recuts':
                $report = $this->recuts($request);

                return $this->generateCSV(
                    collect($report['by_job'])->map(fn ($r) => [$r['job'], $r['work_order'], $r['cuts'], $r['recuts'], $r['recut_rate']]),
                    'cut_station_recuts_by_job',
                    ['Job', 'Work Order', 'Cuts', 'Recuts', 'Recut Rate %']
                );
            case 'cut_station_waste':
                $report = $this->waste($request);

                return $this->generateCSV(
                    collect($report['by_profile'])->map(fn ($r) => [
                        $r['part_name'], $r['finish'], $r['sticks'], $r['stock_inches'], $r['leftover_inches'], $r['leftover_percent'],
                        $r['rack_drops'], $r['rack_drop_inches'], $r['scrap_pieces'], $r['scrap_inches'],
                    ]),
                    'cut_station_waste',
                    ['Profile', 'Finish', 'Sticks', 'Stock Inches', 'Leftover Inches', 'Leftover %', 'Rack Drops', 'Rack Drop Inches', 'Scrap Pieces', 'Scrap Inches']
                );
            default:
                return null;
        }
    }

    private function productivity(Request $request): array
    {
        [$from, $to, $startDate, $endDate] = Stats::range($request->query('start_date'), $request->query('end_date'));
        $activity = Stats::activityBetween($from, $to);
        $cuts = $activity->reject(fn ($e) => $e->is_recut)->values();
        $flags = $activity->where('is_recut', true);

        $byOperator = $activity->groupBy(fn ($e) => Stats::operatorKey($e))->map(function (Collection $rows) {
            $done = $rows->reject(fn ($e) => $e->is_recut)->values();
            $days = $done->map(fn ($e) => Stats::localDate($e->created_at))->unique()->count();
            $recuts = $rows->where('is_recut', true)->count();

            return [
                'operator' => Stats::operatorLabel($rows->last()->operator_name),
                'cuts' => $done->count(),
                'recuts' => $recuts,
                'recut_rate' => Stats::percent($recuts, $done->count()),
                'manual_cuts' => $done->where('is_manual_cut', true)->count(),
                'inches' => round($done->sum(fn ($e) => (float) $e->dimension_inches), 1),
                'active_days' => $days,
                'avg_per_active_day' => $days ? round($done->count() / $days, 1) : 0,
                'first_cut' => $done->first()?->cut_at_local?->format('Y-m-d H:i'),
                'last_cut' => $done->last()?->cut_at_local?->format('Y-m-d H:i'),
            ];
        })->sortByDesc('cuts')->values();

        $byDate = $activity->groupBy(fn ($e) => Stats::localDate($e->created_at))->map(fn (Collection $rows, $date) => [
            'date' => $date,
            'cuts' => $rows->reject(fn ($e) => $e->is_recut)->count(),
            'recuts' => $rows->where('is_recut', true)->count(),
            'operators' => $rows->reject(fn ($e) => $e->is_recut)->map(fn ($e) => Stats::operatorKey($e))->unique()->count(),
        ])->sortKeys()->values();

        $hours = $cuts->countBy(fn ($e) => Stats::localHour($e->created_at));
        $byHour = collect(range(0, 23))->map(fn ($h) => [
            'hour' => $h,
            'label' => sprintf('%d:00', $h),
            'cuts' => $hours->get($h, 0),
        ])->values();

        $activeDays = $byDate->where('cuts', '>', 0)->count();
        $recuts = $flags->count();

        return [
            'summary' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_cuts' => $cuts->count(),
                'recuts' => $recuts,
                'recut_rate' => Stats::percent($recuts, $cuts->count()),
                'manual_cuts' => $cuts->where('is_manual_cut', true)->count(),
                'total_inches' => round($cuts->sum(fn ($e) => (float) $e->dimension_inches), 1),
                'operators' => $byOperator->count(),
                'active_days' => $activeDays,
                'avg_per_active_day' => $activeDays ? round($cuts->count() / $activeDays, 1) : 0,
                'busiest_day' => $byDate->sortByDesc('cuts')->first()['date'] ?? null,
            ],
            'by_operator' => $byOperator,
            'by_date' => $byDate,
            'by_hour' => $byHour,
        ];
    }

    private function recuts(Request $request): array
    {
        [$from, $to, $startDate, $endDate] = Stats::range($request->query('start_date'), $request->query('end_date'));
        $activity = Stats::activityBetween($from, $to);
        $cuts = $activity->reject(fn ($e) => $e->is_recut);

        $rate = fn (Collection $rows) => [
            'cuts' => $rows->reject(fn ($e) => $e->is_recut)->count(),
            'recuts' => $rows->where('is_recut', true)->count(),
            'recut_rate' => Stats::percent($rows->where('is_recut', true)->count(), $rows->reject(fn ($e) => $e->is_recut)->count()),
        ];
        $worstFirst = fn (Collection $rows) => $rows->sortByDesc(fn ($r) => [$r['recuts'], $r['recut_rate']])->values();

        $byJob = $worstFirst($activity->groupBy(fn ($e) => Stats::jobKey($e))->map(fn (Collection $rows) => [
            'job' => Stats::jobLabel($rows->last()->job_name),
            'work_order' => $rows->last()->work_order,
        ] + $rate($rows)));

        $byOperator = $worstFirst($activity->groupBy(fn ($e) => Stats::operatorKey($e))->map(fn (Collection $rows) => [
            'operator' => Stats::operatorLabel($rows->last()->operator_name),
        ] + $rate($rows)));

        $byProfile = $worstFirst($activity->groupBy(fn ($e) => $e->part_name.'|'.$e->finish)->map(fn (Collection $rows) => [
            'part_name' => $rows->first()->part_name ?: 'Unnamed',
            'finish' => $rows->first()->finish,
        ] + $rate($rows)));

        $recuts = $activity->where('is_recut', true)->count();

        return [
            'summary' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_cuts' => $cuts->count(),
                'recuts' => $recuts,
                'recut_rate' => Stats::percent($recuts, $cuts->count()),
                'jobs_with_recuts' => $byJob->where('recuts', '>', 0)->count(),
                'jobs' => $byJob->count(),
            ],
            'by_job' => $byJob,
            'by_operator' => $byOperator,
            'by_profile' => $byProfile,
        ];
    }

    private function waste(Request $request): array
    {
        [$from, $to, $startDate, $endDate] = Stats::range($request->query('start_date'), $request->query('end_date'));

        $sticks = Stats::sticksBetween($from, $to);
        $drops = Stats::dropEntriesBetween($from, $to);
        $isScrap = fn ($e) => str_starts_with((string) $e->description, 'Scrap');

        $profileKey = fn ($row) => $row->part_name.'|'.$row->finish;
        $keys = $sticks->map($profileKey)->merge($drops->map($profileKey))->unique();

        $byProfile = $keys->map(function ($key) use ($sticks, $drops, $profileKey, $isScrap) {
            $profileSticks = $sticks->filter(fn ($s) => $profileKey($s) === $key);
            $profileDrops = $drops->filter(fn ($d) => $profileKey($d) === $key);
            $scrap = $profileDrops->filter($isScrap);
            $rack = $profileDrops->reject($isScrap);
            $stock = (float) $profileSticks->sum(fn ($s) => (float) $s->length_inches);
            $leftover = (float) $profileSticks->sum(fn ($s) => (float) $s->waste_inches);
            $first = $profileSticks->first() ?? $profileDrops->first();

            return [
                'part_name' => $first->part_name ?: 'Unnamed',
                'finish' => $first->finish,
                'sticks' => $profileSticks->count(),
                'stock_inches' => round($stock, 1),
                'leftover_inches' => round($leftover, 1),
                'leftover_percent' => Stats::percent($leftover, $stock),
                'rack_drops' => $rack->count(),
                'rack_drop_inches' => round($rack->sum(fn ($d) => (float) $d->dimension_inches), 1),
                'scrap_pieces' => $scrap->count(),
                'scrap_inches' => round($scrap->sum(fn ($d) => (float) $d->dimension_inches), 1),
            ];
        })->sortByDesc('scrap_inches')->values();

        $stock = $byProfile->sum('stock_inches');
        $scrapInches = $byProfile->sum('scrap_inches');

        return [
            'summary' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'sticks' => $byProfile->sum('sticks'),
                'stock_inches' => round($stock, 1),
                'leftover_inches' => round($byProfile->sum('leftover_inches'), 1),
                'leftover_percent' => Stats::percent($byProfile->sum('leftover_inches'), $stock),
                'rack_drops' => $byProfile->sum('rack_drops'),
                'scrap_pieces' => $byProfile->sum('scrap_pieces'),
                'scrap_inches' => round($scrapInches, 1),
                'scrap_percent' => Stats::percent($scrapInches, $stock),
            ],
            'by_profile' => $byProfile,
        ];
    }
}
