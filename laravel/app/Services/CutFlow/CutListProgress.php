<?php

namespace App\Services\CutFlow;

use App\Models\CutFlow\CutLogEntry;
use App\Models\CutFlow\Part;
use Illuminate\Support\Collection;

/**
 * Progress of one or more cut lists by part number (+ finish): how many of the pieces have been cut,
 * and a projection of the time still needed.
 *
 * Pace is measured from the cut log, not guessed: the gap between two consecutive cuts by the same
 * operator, ignoring gaps longer than BREAK_SECONDS (breaks, lunch, end of shift). Pace comes from
 * the lists themselves when they have enough cuts, otherwise from the last HISTORY_DAYS of the whole
 * shop, so a list nobody has started still gets an estimate. Medians, so one slow cut doesn't skew it.
 */
class CutListProgress
{
    /** A gap longer than this between two cuts is a break, not cutting time. */
    public const BREAK_SECONDS = 300;

    /** Fewest gap samples needed before a profile / list uses its own pace. */
    public const MIN_SAMPLES = 5;

    public const HISTORY_DAYS = 60;

    /**
     * @param  array<int>  $jobIds
     * @return array{summary: array, by_part: Collection}
     */
    public static function forJobs(array $jobIds): array
    {
        $parts = Part::query()->whereIn('cut_job_id', $jobIds)
            ->get(['id', 'cut_job_id', 'name', 'finish', 'qty_original', 'qty_remaining']);

        $listGaps = self::gaps(CutLogEntry::query()->whereIn('cut_job_id', $jobIds));
        $shopGaps = self::gaps(CutLogEntry::query()->where('created_at', '>=', now()->subDays(self::HISTORY_DAYS)));

        // Overall pace, and where it came from, so the UI can say how much to trust the estimate.
        [$basePace, $baseSource] = match (true) {
            $listGaps->flatten()->count() >= self::MIN_SAMPLES => [self::median($listGaps->flatten()), 'this cut list'],
            $shopGaps->flatten()->count() >= self::MIN_SAMPLES => [self::median($shopGaps->flatten()), 'recent shop history'],
            default => [null, null],
        };

        $byPart = $parts->groupBy(fn ($p) => $p->name.'|'.$p->finish)->map(function (Collection $rows, $key) use ($listGaps, $shopGaps, $basePace) {
            $total = (int) $rows->sum('qty_original');
            $remaining = min($total, max(0, (int) $rows->sum('qty_remaining')));
            $done = $total - $remaining;

            $own = $listGaps->get($key) ?? collect();
            $pace = $own->count() >= self::MIN_SAMPLES ? self::median($own)
                : ((($shopGaps->get($key)?->count()) ?? 0) >= self::MIN_SAMPLES ? self::median($shopGaps->get($key)) : $basePace);

            return [
                'part_number' => $rows->first()->name,
                'finish' => $rows->first()->finish,
                'total_cuts' => $total,
                'done_cuts' => $done,
                'remaining_cuts' => $remaining,
                'percent' => CutStationStats::percent($done, $total),
                'seconds_per_cut' => $pace,
                'remaining_seconds' => $pace === null ? null : (int) round($remaining * $pace),
            ];
        })->sortBy([
            fn ($a, $b) => $b['total_cuts'] <=> $a['total_cuts'],
            fn ($a, $b) => strcmp((string) $a['part_number'], (string) $b['part_number']),
        ])->values();

        $total = $byPart->sum('total_cuts');
        $done = $byPart->sum('done_cuts');
        $estimable = $byPart->filter(fn ($r) => $r['remaining_cuts'] > 0 && $r['remaining_seconds'] === null)->isEmpty();

        return [
            'summary' => [
                'total_cuts' => $total,
                'done_cuts' => $done,
                'remaining_cuts' => $total - $done,
                'percent' => CutStationStats::percent($done, $total),
                'part_numbers' => $byPart->count(),
                'pace_source' => $baseSource,
                'seconds_per_cut' => $basePace,
                // Null when there is no usable pace anywhere: better no estimate than an invented one.
                'remaining_seconds' => $estimable ? (int) $byPart->sum('remaining_seconds') : null,
            ],
            'by_part' => $byPart,
        ];
    }

    /** "2 h 15 min" / "45 min" / "<1 min"; "—" when there is no estimate. */
    public static function duration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }
        $minutes = (int) round($seconds / 60);
        if ($minutes < 1) {
            return $seconds > 0 ? '<1 min' : '0 min';
        }

        return intdiv($minutes, 60) > 0
            ? intdiv($minutes, 60).' h '.($minutes % 60).' min'
            : $minutes.' min';
    }

    /**
     * Seconds between consecutive cuts by the same operator, keyed by "part|finish" of the cut that
     * ended the gap (that's the cut whose duration it measures).
     *
     * @return Collection<string, Collection<int, float>>
     */
    private static function gaps($query): Collection
    {
        $entries = $query->where('type', '!=', 'drop')->where('is_reprint', false)->where('is_recut', false)
            ->orderBy('created_at')->get(['created_at', 'operator_id', 'operator_name', 'part_name', 'finish']);

        $last = [];
        $out = collect();
        foreach ($entries as $e) {
            $operator = $e->operator_id ?: ($e->operator_name ?: 'none');
            $prev = $last[$operator] ?? null;
            $last[$operator] = $e->created_at;
            if ($prev === null) {
                continue;
            }
            $gap = $prev->diffInSeconds($e->created_at, true);
            if ($gap > 0 && $gap <= self::BREAK_SECONDS) {
                $key = $e->part_name.'|'.$e->finish;
                $out->put($key, ($out->get($key) ?? collect())->push($gap));
            }
        }

        return $out;
    }

    private static function median(Collection $values): float
    {
        $sorted = $values->sort()->values();
        $n = $sorted->count();

        return $n % 2 ? (float) $sorted[intdiv($n, 2)] : ($sorted[$n / 2 - 1] + $sorted[$n / 2]) / 2;
    }
}
