<?php

namespace App\Services\Pm;

use App\Models\DrawingElevation;

/**
 * Do the sheets of a long elevation (a run drawn across several sheets) agree with each other?
 *
 * Each sheet owns what lies between its numbered break lines and also draws a little of its neighbours for context,
 * so the same lites are read twice, once as context on one sheet and once as owned on the next. If the readings are
 * right they agree: the lites a sheet draws BEYOND break line n are the first lites its neighbour owns inside that
 * line, in the same order and size. Heights run the full length of a run, so every sheet must give the same R.O. and
 * F.S. height. Disagreement means one of the sheets was misread (or drawn differently), and the run's lite count and
 * width cannot be trusted until a person looks.
 *
 * Nothing here changes a value; it reports. Uses the values a person has confirmed or corrected when there are any.
 */
class DrawingRunChecks
{
    private const TOL = 0.01;     // inches

    /** @return array|null null when the elevation is not a multi-sheet run */
    public static function forElevation(DrawingElevation $e): ?array
    {
        if (! $e->multi_sheet) {
            return null;
        }
        $e->loadMissing(['sheets.breaks', 'marks']);
        $sheets = $e->sheets->sortBy('page')->values();
        $eff = fn ($m) => (float) ($m->confirmed_value ?? $m->value);
        $cx = fn ($m) => ((float) $m->x0 + (float) $m->x1) / 2;
        $lites = fn (int $page) => $e->marks->where('page', $page)->where('kind', 'dlo_w');

        // what each sheet owns, and the run's total
        $per = $sheets->map(function ($sh) use ($lites, $eff) {
            $own = $lites($sh->page)->where('counted', true);

            return ['page' => $sh->page, 'lites' => $own->count(), 'width' => round($own->sum($eff), 4),
                'breaks' => $sh->breaks->map(fn ($b) => ['num' => $b->num, 'x' => (float) $b->x])->all()];
        })->all();

        // one check per numbered break line shared by two sheets: the one that draws it at its right edge, the one at its left
        $breaks = [];
        $nums = $sheets->flatMap(fn ($sh) => $sh->breaks->pluck('num'))->filter(fn ($n) => $n !== null)->unique()->sort()->values();
        foreach ($nums as $n) {
            $left = $right = null;       // $left: the sheet to the LEFT of the line (line at its right); $right: the sheet to the right
            foreach ($sheets as $sh) {
                $b = $sh->breaks->firstWhere('num', $n);
                if (! $b) {
                    continue;
                }
                if ((float) $b->x > (float) $sh->width / 2) {
                    $left = $left ?: [$sh, (float) $b->x];
                } else {
                    $right = $right ?: [$sh, (float) $b->x];
                }
            }
            if (! $left || ! $right) {
                continue;
            }
            [$A, $ax] = $left;
            [$B, $bx] = $right;
            $aCtx = $lites($A->page)->where('counted', false)->filter(fn ($m) => $cx($m) > $ax)->sortBy(fn ($m) => $cx($m))->values();          // beyond the line, nearest first
            $bOwn = $lites($B->page)->where('counted', true)->sortBy(fn ($m) => $cx($m))->values();
            $bCtx = $lites($B->page)->where('counted', false)->filter(fn ($m) => $cx($m) < $bx)->sortByDesc(fn ($m) => $cx($m))->values();      // beyond the line, nearest first
            $aOwn = $lites($A->page)->where('counted', true)->sortByDesc(fn ($m) => $cx($m))->values();

            $pairs = [];
            foreach ($aCtx as $i => $m) {
                if (isset($bOwn[$i])) {
                    $pairs[] = [$A->page, $eff($m), $B->page, $eff($bOwn[$i])];
                }
            }
            foreach ($bCtx as $i => $m) {
                if (isset($aOwn[$i])) {
                    $pairs[] = [$B->page, $eff($m), $A->page, $eff($aOwn[$i])];
                }
            }
            $bad = collect($pairs)->filter(fn ($p) => abs($p[1] - $p[3]) > self::TOL)->values();
            $breaks[] = [
                'num' => $n, 'sheets' => [$A->page, $B->page], 'compared' => count($pairs),
                'ok' => $pairs ? $bad->isEmpty() : null,          // null: neither sheet draws any of the other, so there is nothing to compare
                'mismatches' => $bad->map(fn ($p) => ['drawn_on' => $p[0], 'drawn' => $p[1], 'owned_on' => $p[2], 'owned' => $p[3]])->all(),
            ];
        }

        // heights run the whole length of the run: every sheet that prints one must print the same
        $heights = [];
        foreach (['ro_h' => 'R.O. height', 'fs_h' => 'F.S. height'] as $kind => $label) {
            $vals = $sheets->map(function ($sh) use ($e, $kind, $eff) {
                $v = $e->marks->where('page', $sh->page)->where('kind', $kind)->map($eff)->sort()->first();

                return $v === null ? null : ['page' => $sh->page, 'value' => $v];
            })->filter()->values();
            if ($vals->count() > 1) {
                $heights[] = ['kind' => $kind, 'label' => $label, 'values' => $vals->all(), 'ok' => $vals->pluck('value')->max() - $vals->pluck('value')->min() <= self::TOL];
            }
        }

        $failed = collect($breaks)->contains(fn ($b) => $b['ok'] === false) || collect($heights)->contains(fn ($h) => ! $h['ok']);

        return ['sheets' => $per, 'total_lites' => array_sum(array_column($per, 'lites')), 'total_width' => round(array_sum(array_column($per, 'width')), 4),
            'breaks' => $breaks, 'heights' => $heights, 'ok' => ! $failed];
    }
}
