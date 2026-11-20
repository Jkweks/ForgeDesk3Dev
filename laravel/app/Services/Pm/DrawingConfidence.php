<?php

namespace App\Services\Pm;

use App\Models\DrawingDimMark;
use App\Models\DrawingElevation;

/**
 * How far to trust what the import read, and why. A transparent score (0 to 100) with reasons, not a model:
 * the strongest evidence is that the frame closes arithmetically (the readings agree with each other); a person's
 * confirmation counts most; unreadable or implausible values, missing key sizes and parser warnings lower it.
 */
class DrawingConfidence
{
    public const HIGH = 85;

    public const MEDIUM = 60;

    public static function level(int $score): string
    {
        return $score >= self::HIGH ? 'high' : ($score >= self::MEDIUM ? 'medium' : 'low');
    }

    private static function onGrid(float $v): bool
    {
        return abs($v - round($v * 16) / 16) < 1e-6;
    }

    /** Plausible range in inches for a reading of this kind. */
    private static function plausible(string $kind, float $v): bool
    {
        return match (true) {
            str_starts_with($kind, 'dlo') => $v >= 6 && $v <= 160,
            str_starts_with($kind, 'do') => $v >= 24 && $v <= 156,
            default => $v >= 12 && $v <= 4000,        // R.O. / F.S. (a curtain wall run can be very long)
        };
    }

    /** @return array{score: int, level: string, reasons: string[]} */
    public static function forMark(DrawingDimMark $m, ?string $axisState): array
    {
        if ($m->status !== 'suggested') {
            return ['score' => 100, 'level' => 'high', 'reasons' => [$m->status === 'confirmed' ? 'Confirmed by a person' : 'Corrected by a person']];
        }
        $score = match ($axisState) {
            'closes', 'corrected' => 92, 'accepted' => 55, 'unverified' => 45, 'flagged' => 30, default => 65,
        };
        $reasons = [match ($axisState) {
            'closes', 'corrected' => 'The frame closes on these readings',
            'flagged' => 'The frame does not close',
            'accepted' => 'Frame does not close; accepted by a person',
            'unverified' => 'Frame does not close; left unverified',
            default => 'Nothing to check this reading against',
        }];
        $v = (float) $m->value;
        if (! self::onGrid($v)) {
            $score -= 20;
            $reasons[] = 'Not on a 1/16" grid, so a digit may have been misread';
        }
        if (! self::plausible($m->kind, $v)) {
            $score -= 25;
            $reasons[] = 'Outside the range this kind of dimension normally has';
        }

        return ['score' => max(0, min(100, $score)), 'level' => self::level(max(0, min(100, $score))), 'reasons' => $reasons];
    }

    /** @return array{score: int, level: string, reasons: string[]} */
    public static function forElevation(DrawingElevation $e): array
    {
        $score = 100;
        $reasons = [];
        $checks = $e->axisChecks->keyBy('axis');

        foreach (['w' => 'Width', 'h' => 'Height'] as $axis => $label) {
            $state = $checks[$axis]->state ?? 'unverifiable';
            [$pen, $why] = match ($state) {
                'closes' => [0, null],
                'corrected' => [0, "{$label}: corrected by a person and now closes"],
                'flagged' => [35, "{$label}: the frame does not close"],
                'unverified' => [25, "{$label}: does not close, left unverified"],
                'accepted' => [12, "{$label}: does not close, accepted with a reason"],
                default => [$e->multi_sheet ? 8 : 12, "{$label}: could not be checked".($e->multi_sheet ? ' (runs across several sheets)' : ' (no frame size or nothing in it)')],
            };
            $score -= $pen;
            if ($why) {
                $reasons[] = $why;
            }
        }

        $marks = $e->marks->where('counted', true);   // readings beyond a sheet's break lines are context, judged on the sheet that owns them
        if ($e->ro_w === null && ! $marks->contains('kind', 'ro_w')) {
            $score -= 6;
            $reasons[] = 'R.O. width not read';
        }
        if ($e->ro_h === null && ! $marks->contains('kind', 'ro_h')) {
            $score -= 6;
            $reasons[] = 'R.O. height not read';
        }
        $open = $marks->filter(fn ($m) => $m->status === 'suggested');
        $offGrid = $open->filter(fn ($m) => ! self::onGrid((float) $m->value))->count();
        if ($offGrid) {
            $score -= min(15, 3 * $offGrid);
            $reasons[] = "{$offGrid} reading".($offGrid === 1 ? '' : 's').' off the 1/16" grid';
        }
        $odd = $open->filter(fn ($m) => ! self::plausible($m->kind, (float) $m->value))->count();
        if ($odd) {
            $score -= min(15, 5 * $odd);
            $reasons[] = "{$odd} implausible reading".($odd === 1 ? '' : 's');
        }
        $run = DrawingRunChecks::forElevation($e);
        if ($run && ! $run['ok']) {
            $score -= 20;
            $reasons[] = 'The sheets of this run disagree with each other (see the run check)';
        }
        if ($e->manual_takeoff) {
            $score -= 10;
            $reasons[] = 'Manual takeoff: '.$e->manual_takeoff;
        }
        if ($marks->count() === 0) {
            $score -= 15;
            $reasons[] = 'No dimensions were located on the sheet';
        }

        // people count most: every value someone confirmed or corrected moves the score toward certainty
        if ($marks->count()) {
            $checked = $marks->filter(fn ($m) => $m->status !== 'suggested')->count();
            if ($checked) {
                $share = $checked / $marks->count();
                $score += (int) round((100 - $score) * $share * 0.7);
                $reasons[] = round($share * 100).'% of the values checked by a person';
            }
        }

        $score = max(0, min(100, $score));

        return ['score' => $score, 'level' => self::level($score), 'reasons' => $reasons ?: ['Frame closes and the readings look plausible']];
    }
}
