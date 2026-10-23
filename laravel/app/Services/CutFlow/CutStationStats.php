<?php

namespace App\Services\CutFlow;

use App\Models\CutFlow\CutLogEntry;
use App\Models\CutFlow\StickSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Read-side numbers over the cut station's history (cut_log_entries), shared by the
 * dashboard widget and the cut station reports so both agree on what a "cut" is.
 *
 * A cut is a piece the saw actually cut for a part: type 'planned' or 'manual'. Excluded:
 * type 'drop' (drop-rack cuts, rack tags and scrap tags — material, not parts), is_reprint rows
 * (a label reprint, no cut) and is_recut rows. A recut row is written when a piece is *flagged*
 * for recut, not when it is cut; the redo later logs as an ordinary cut, so counting the flag
 * too would count every recut twice. Recuts are therefore counted from the flags (recuts()),
 * and each one has its redo cut among the cuts.
 *
 * Calendar boundaries are shop-local; timestamps are stored in UTC.
 */
class CutStationStats
{
    public const SHOP_TIMEZONE = 'America/New_York';

    public const PERIODS = ['day' => 'Today', 'month' => 'This calendar month', 'year' => 'This calendar year'];

    /** @return Builder<CutLogEntry> */
    public static function cuts(): Builder
    {
        return CutLogEntry::query()->where('type', '!=', 'drop')->where('is_reprint', false)->where('is_recut', false);
    }

    /** Pieces flagged for recut (see class note). @return Builder<CutLogEntry> */
    public static function recuts(): Builder
    {
        return CutLogEntry::query()->where('type', '!=', 'drop')->where('is_reprint', false)->where('is_recut', true);
    }

    /** Start of the shop-local day/month/year containing $now, as a UTC instant. */
    public static function periodStart(string $period, ?Carbon $now = null): Carbon
    {
        $local = ($now ?? now())->clone()->setTimezone(self::SHOP_TIMEZONE);

        $start = match ($period) {
            'month' => $local->startOfMonth(),
            'year' => $local->startOfYear(),
            default => $local->startOfDay(),
        };

        return $start->setTimezone('UTC');
    }

    /** Human label for the period containing $now ("Oct 21, 2026", "October 2026", "2026"). */
    public static function periodCaption(string $period, ?Carbon $now = null): string
    {
        $local = ($now ?? now())->clone()->setTimezone(self::SHOP_TIMEZONE);

        return match ($period) {
            'month' => $local->format('F Y'),
            'year' => $local->format('Y'),
            default => $local->format('M j, Y'),
        };
    }

    public static function totalSince(Carbon $since): int
    {
        return self::cuts()->where('created_at', '>=', $since)->count();
    }

    /**
     * Shop-local inclusive date range as UTC instants. Defaults to the last 30 days; a reversed
     * range is swapped; unparseable input falls back to the default.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string, 3: string} [from, to, startDate, endDate]
     */
    public static function range(?string $start, ?string $end): array
    {
        $parse = function (?string $value, Carbon $fallback): Carbon {
            try {
                return $value ? Carbon::parse($value, self::SHOP_TIMEZONE)->startOfDay() : $fallback;
            } catch (\Throwable) {
                return $fallback;
            }
        };

        $today = now()->setTimezone(self::SHOP_TIMEZONE)->startOfDay();
        $from = $parse($start, $today->clone()->subDays(29));
        $to = $parse($end, $today);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [
            $from->clone()->setTimezone('UTC'),
            $to->clone()->endOfDay()->setTimezone('UTC'),
            $from->toDateString(),
            $to->toDateString(),
        ];
    }

    /**
     * Cuts and recut flags in the range (told apart by is_recut), narrowed to the columns the reports
     * need, oldest first. Aggregation happens in PHP so the shop-local day/hour bucketing is identical
     * on every database.
     *
     * @return \Illuminate\Support\Collection<int, CutLogEntry>
     */
    public static function activityBetween(Carbon $from, Carbon $to)
    {
        return CutLogEntry::query()
            ->where('type', '!=', 'drop')->where('is_reprint', false)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get(['id', 'created_at', 'operator_id', 'operator_name', 'cut_job_id', 'job_name', 'work_order',
                'part_name', 'finish', 'dimension_inches', 'is_recut', 'is_manual_cut']);
    }

    public static function operatorKey(CutLogEntry $e): string
    {
        return $e->operator_id ? 'u'.$e->operator_id : 'none';
    }

    public static function operatorLabel(?string $name): string
    {
        return $name ?: 'Not signed in';
    }

    public static function jobKey(CutLogEntry $e): string
    {
        return $e->cut_job_id ? 'j'.$e->cut_job_id : 'none';
    }

    public static function jobLabel(?string $name): string
    {
        return $name ?: 'Manual / no cut list';
    }

    public static function percent(int|float $part, int|float $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }

    public static function localDate(Carbon $utc): string
    {
        return $utc->clone()->setTimezone(self::SHOP_TIMEZONE)->toDateString();
    }

    public static function localHour(Carbon $utc): int
    {
        return (int) $utc->clone()->setTimezone(self::SHOP_TIMEZONE)->format('G');
    }

    /** Completed, non-cancelled sticks in the range (by last update = when it finished). */
    public static function sticksBetween(Carbon $from, Carbon $to)
    {
        return StickSession::query()
            ->where('status', 'complete')
            ->whereNull('cancelled_at')
            ->whereBetween('updated_at', [$from, $to])
            ->get(['id', 'part_name', 'finish', 'length_label', 'length_inches', 'waste_inches']);
    }

    /** Drop-rack tags and scrap tags the station recorded in the range (type 'drop'). */
    public static function dropEntriesBetween(Carbon $from, Carbon $to)
    {
        return CutLogEntry::query()
            ->where('type', 'drop')
            ->where('is_reprint', false)
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'part_name', 'finish', 'description', 'dimension_inches', 'stick_session_id']);
    }
}
