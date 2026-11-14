<?php

namespace App\Services\Pm;

use App\Models\DrawingAxisCheck;
use App\Models\DrawingDimMark;
use App\Models\DrawingElevation;

/**
 * Frame-closure checks for imported drawings: frame size = openings + members, with one uniform member of
 * 1" to 4 1/2" on a 1/16" grid (or the members the sheet prints). The arithmetic is the PM Suite importer's own,
 * run by pm-worker; this class stores the import's verdict and re-runs it when a person corrects a value.
 */
class DrawingChecks
{
    public function __construct(private PmWorkerClient $worker) {}

    /** Store what the parser concluded for each axis, and the member rows the sheet prints. */
    public function storeFromImport(DrawingElevation $row, array $e): void
    {
        $multi = (bool) ($e['multiSheet'] ?? false);
        foreach (['w', 'h'] as $axis) {
            $b = $multi ? null : ($e['balance'][$axis] ?? null);   // a merged run is never judged: the frame closes per sheet
            $row->axisChecks()->create([
                'axis' => $axis,
                'ok' => $b === null ? null : (bool) ($b['ok'] ?? false),
                'member' => is_numeric($b['member'] ?? null) ? $b['member'] : null,
                'used' => (int) ($b['used'] ?? 0), 'dropped' => (int) ($b['dropped'] ?? 0),
                'chains' => is_array($b['chains'] ?? null) ? count($b['chains']) : ($b === null ? 0 : 1),
                'printed' => (bool) (($b['chains'][0]['printed'] ?? false) || ($b['printed'] ?? false)),
            ]);
        }
        foreach (array_values($e['printedMembersW'] ?? []) as $r => $members) {
            foreach (array_values((array) $members) as $p => $v) {
                if (is_numeric($v)) {
                    $row->printedMembers()->create(['row' => $r, 'position' => $p, 'value' => $v]);
                }
            }
        }
    }

    /**
     * Re-run the check for one axis on the CURRENT values (a person's correction wins over the reading).
     * Updates the stored check. A resolution made against an earlier verdict is dropped when the verdict changes.
     */
    public function recheck(DrawingElevation $row, string $axis): ?DrawingAxisCheck
    {
        $check = $row->axisChecks()->where('axis', $axis)->first();
        if (! $check || $row->multi_sheet) {
            return $check;     // a merged run stays unjudged
        }

        $marks = $row->marks()->get();
        $eff = fn (DrawingDimMark $m) => ['v' => (float) ($m->confirmed_value ?? $m->value), 'rect' => [(float) $m->x0, (float) $m->top, (float) $m->x1, (float) $m->bottom]];
        $fs = $marks->firstWhere('kind', "fs_{$axis}");
        $payload = [
            'axis' => $axis,
            'fs' => $fs ? $eff($fs) : null,
            'dos' => $marks->where('kind', "do_{$axis}")->map($eff)->values()->all(),
            'dlos' => $marks->where('kind', "dlo_{$axis}")->map($eff)->values()->all(),
        ];
        if ($axis === 'w') {
            $payload['printed_members'] = $row->printedMembers->groupBy('row')->map(fn ($g) => $g->pluck('value')->map(fn ($v) => (float) $v)->values()->all())->values()->all();
        }

        $edited = $marks->contains(fn ($m) => $m->kind && str_ends_with($m->kind, "_{$axis}") && $m->confirmed_value !== null
            && abs((float) $m->confirmed_value - (float) $m->value) >= 0.01);
        $r = $this->worker->balance($payload);

        $before = $check->ok;
        $check->fill([
            'ok' => $r === null ? null : (bool) $r['ok'],
            'member' => is_numeric($r['member'] ?? null) ? $r['member'] : null,
            'used' => (int) ($r['used'] ?? 0), 'dropped' => (int) ($r['dropped'] ?? 0),
            'printed' => (bool) ($r['chains'][0]['printed'] ?? false),
            'edited' => $edited,
        ]);
        if ($before !== $check->ok) {
            $check->fill(['resolution' => null, 'resolution_reason' => null, 'resolved_by' => null, 'resolved_at' => null]);
        }
        $check->save();

        return $check;
    }

    /** Axes that still block approval: the frame does not close and nobody has dealt with it. */
    public static function unresolved(DrawingElevation $row): array
    {
        return $row->axisChecks->filter(fn ($c) => $c->state === 'flagged')->pluck('axis')->values()->all();
    }
}
