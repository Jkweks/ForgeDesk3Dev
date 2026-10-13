<?php

namespace App\Services\CutFlow;

use App\Models\Product;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Sill quarter-point report. From the headerless 13-column fabrication cut-list CSV, works out for every
 * SILL in every elevation its start, end, 1/4 and 3/4 point measured from the left edge of the assembly,
 * and checks each run's layout width against the SILL RECEPTORS CHANNEL lengths.
 *
 * The CutFlow cut list itself can't feed this (it drops bay numbers and merges lines), so the raw CSV is
 * kept at upload time (storeUpload()) and the report is built from it on demand — which also means a
 * changed product width is picked up by the next download.
 *
 * All arithmetic is integer: lengths are held in 1/100,000,000 inch (UNIT), which is exact for any 1/64"
 * value and its quarters. Only formatting converts back to decimals/fractions.
 */
class SillQuarterPointService
{
    private const UNIT = 100_000_000;

    private const DOOR_TAGS = ['{SIDELITE JAMB}', '{TERMINATING JAMB}'];

    /** Upper bound on receptor-piece assignments tried per elevation before the check is skipped. */
    private const MAX_PARTITIONS = 300_000;

    private array $cfg;

    /** @var Closure(string): ?float  part number -> face width in inches, or null when unknown */
    private Closure $productWidth;

    private array $widthCache = [];

    public function __construct(?array $config = null, ?Closure $productWidth = null)
    {
        $this->cfg = $config ?? config('sill_quarter_points');
        $this->productWidth = $productWidth ?? function (string $partNumber): ?float {
            $w = Product::where('part_number', $partNumber)->whereNotNull('dimension_width')->value('dimension_width');

            return $w === null ? null : (float) $w;
        };
    }

    // ---------------------------------------------------------------- storage

    private static function storagePath(int $cutJobId): string
    {
        return "sill_quarter_points/cut-job-{$cutJobId}.csv";
    }

    /**
     * Keeps the uploaded CSV for later report generation when it contains sills in the fabrication-package
     * format. Returns a short summary, or null when the file has nothing for this report (any other CSV layout).
     *
     * @return array{elevations: int, needs_review: int}|null
     */
    public function storeUpload(UploadedFile $file, int $cutJobId): ?array
    {
        $report = $this->build($file->getRealPath());
        if ($report['elevations'] === [] && $report['needs_review'] === []) {
            Storage::disk('local')->delete(self::storagePath($cutJobId));

            return null;
        }

        Storage::disk('local')->put(self::storagePath($cutJobId), file_get_contents($file->getRealPath()));

        return ['elevations' => count($report['elevations']), 'needs_review' => count($report['needs_review'])];
    }

    public function hasStoredUpload(int $cutJobId): bool
    {
        return Storage::disk('local')->exists(self::storagePath($cutJobId));
    }

    public function buildForCutJob(int $cutJobId): ?array
    {
        if (! $this->hasStoredUpload($cutJobId)) {
            return null;
        }

        return $this->build(Storage::disk('local')->path(self::storagePath($cutJobId)));
    }

    // ---------------------------------------------------------------- parsing

    /**
     * @return array<string, array{project: string, work_order: string, elevation: string, rows: array}>
     */
    public function parse(string $path): array
    {
        $groups = [];
        $handle = fopen($path, 'r');
        while (($f = fgetcsv($handle, escape: '')) !== false) {
            if ($f === [null] || count($f) < 10) {
                continue;
            }
            $f = array_map(fn ($v) => trim((string) $v), $f);
            if (! is_numeric($f[2]) || $f[6] === '') {
                continue; // header row or junk
            }

            preg_match_all('/\{[^}]*\}/', $f[9], $m);
            $tags = array_map('strtoupper', $m[0]);
            $base = strtoupper(trim(preg_replace('/\s+/', ' ', preg_replace('/\{[^}]*\}/', '', $f[9])), ' ,'));

            $key = $f[4].'|'.$f[5].'|'.$f[6];
            $groups[$key] ??= ['project' => $f[4], 'work_order' => $f[5], 'elevation' => $f[6], 'rows' => []];
            $groups[$key]['rows'][] = [
                'pn' => $f[0],
                'len' => $this->units($f[2]),
                'qty' => max(1, (int) $f[3]),
                'bay' => ctype_digit($f[7]) ? (int) $f[7] : null,
                'desc' => $base,
                'tags' => $tags,
                'door_member' => array_intersect($tags, self::DOOR_TAGS) !== [],
            ];
        }
        fclose($handle);

        return $groups;
    }

    // ---------------------------------------------------------------- report

    /**
     * @return array{constants: array, elevations: array, needs_review: array, no_sills: array,
     *   assumptions: array, receptor_flags: array}
     */
    public function build(string $path): array
    {
        $this->widthCache = [];
        $out = ['constants' => $this->cfg, 'elevations' => [], 'needs_review' => [], 'no_sills' => [], 'assumptions' => [], 'receptor_flags' => []];

        $groups = $this->parse($path);
        // Project, work order, then elevation in natural order (4-E3 before 11-E9); everything below follows this order.
        uasort($groups, fn ($a, $b) => [$a['project'], $a['work_order']] <=> [$b['project'], $b['work_order']]
            ?: strnatcasecmp($a['elevation'], $b['elevation']));

        foreach ($groups as $group) {
            $label = trim($group['project'].' / '.$group['work_order'].' / '.$group['elevation'], ' /');
            if (! collect($group['rows'])->contains(fn ($r) => $r['desc'] === 'SILL')) {
                if (collect($group['rows'])->contains(fn ($r) => $r['desc'] === 'THRESHOLD' || str_contains($r['desc'], 'JAMB'))) {
                    $out['no_sills'][] = $label;
                }

                continue;
            }

            try {
                $el = $this->analyze($group);
            } catch (ReviewException $e) {
                $out['needs_review'][] = ['label' => $label, 'project' => $group['project'], 'work_order' => $group['work_order'], 'elevation' => $group['elevation'], 'reason' => $e->getMessage()];

                continue;
            }

            foreach ($el['defaults'] as $d) {
                $out['assumptions'][] = "{$el['elevation']}: part {$d['pn']} ({$d['use']}) has no face width — assumed {$this->dec($d['width'])}\".";
            }
            foreach ($el['runs'] as $run) {
                if ($run['receptor'] && $run['receptor']['flagged']) {
                    $out['receptor_flags'][] = [
                        'elevation' => $el['elevation'], 'run' => $run['label'],
                        'diff' => $run['receptor']['diff_dec'], 'layout' => $run['receptor']['layout_dec'], 'receptor' => $run['receptor']['receptor_dec'],
                    ];
                }
            }
            if ($el['receptor_note']) {
                $out['assumptions'][] = "{$el['elevation']}: {$el['receptor_note']}";
            }
            $out['elevations'][] = $el;
        }

        $out['assumptions'] = array_values(array_unique($out['assumptions']));

        return $out;
    }

    private function analyze(array $group): array
    {
        $rows = collect($group['rows']);
        $cfg = $this->cfg;

        $byDesc = fn (string $d, bool $withDoor = false) => $rows->filter(fn ($r) => $r['desc'] === $d && ($withDoor || ! $r['door_member']))->values();

        $sills = [];
        foreach ($byDesc('SILL') as $r) {
            if ($r['bay'] === null) {
                throw new ReviewException('A SILL row has no bay number.');
            }
            if (isset($sills[$r['bay']])) {
                throw new ReviewException("Bay {$r['bay']} has more than one SILL row.");
            }
            $sills[$r['bay']] = $r;
        }
        $doorBays = [];
        foreach ($byDesc('THRESHOLD', true) as $r) {
            if ($r['bay'] !== null && ! isset($sills[$r['bay']])) {
                $doorBays[$r['bay']] = true;
            }
        }
        $verts = [];
        foreach ($byDesc('FEMALE VERTICAL') as $r) {
            if ($r['bay'] === null || isset($verts[$r['bay']])) {
                throw new ReviewException('FEMALE VERTICAL rows are missing a bay number or duplicated.');
            }
            $verts[$r['bay']] = $r;
        }
        $lefts = $byDesc('LEFT JAMB');
        $rights = $byDesc('RIGHT JAMB');
        if ($lefts->count() !== 1 || $rights->count() !== 1) {
            throw new ReviewException("Expected exactly one LEFT JAMB and one RIGHT JAMB, found {$lefts->count()} and {$rights->count()}.");
        }
        $leftJamb = $lefts->first();
        $rightJamb = $rights->first();

        $allBays = array_unique(array_merge(array_keys($sills), array_keys($doorBays)));
        sort($allBays);
        $N = (int) end($allBays);
        if ($allBays !== range(1, $N)) {
            $missing = array_values(array_diff(range(1, $N), $allBays));
            throw new ReviewException('Bay numbers do not line up (no sill or door for bay '.implode(', ', $missing).').');
        }
        if (isset($doorBays[1])) {
            throw new ReviewException('Door bay is at the far left (bay 1); layout model does not cover this.');
        }

        // --- widths (product data first, then config table, then default)
        $defaults = [];
        $width = function (string $pn, string $use, int|float $fallback) use (&$defaults) {
            [$w, $source] = $this->partWidth($pn, $fallback);
            if ($source === 'default') {
                $defaults[$pn.'|'.$use] = ['pn' => $pn, 'use' => $use, 'width' => $w];
            }

            return [$w, $source];
        };
        [$leftW, $leftSrc] = $width($leftJamb['pn'], 'left jamb', $cfg['default_jamb_width']);
        [$rightW, $rightSrc] = $width($rightJamb['pn'], 'right jamb', $cfg['default_jamb_width']);
        $vertW = [];
        foreach ($verts as $bay => $v) {
            $vertW[$bay] = $width($v['pn'], 'vertical', $cfg['vertical_width'])[0];
        }
        $vwidth = fn (int $bay) => $vertW[$bay] ?? throw new ReviewException("Missing FEMALE VERTICAL for bay {$bay}.");

        $tol = $this->units($cfg['knee_wall_tolerance']);
        $gapL = $this->units($cfg['left_gap']);
        $gapR = $this->units($cfg['right_gap']);

        // --- runs
        $runs = [];
        $breaks = [];
        $cur = null;
        $nextStart = $gapL + $leftW;
        $startKind = 'left jamb';
        $close = function (string $kind, int $boundary) use (&$cur, &$runs) {
            $last = end($cur['sills']);
            $cur['end_kind'] = $kind;
            $cur['layout_width'] = $last['end'] + $boundary;
            $runs[] = $cur;
            $cur = null;
        };

        for ($k = 1; $k <= $N; $k++) {
            if (isset($doorBays[$k])) {
                if ($cur) {
                    $close('comp jamb at door', 0);
                }
                $breaks[] = "Door bay {$k}";
                $nextStart = 0;
                $startKind = "comp jamb at door (bay {$k})";

                continue;
            }

            $sill = $sills[$k];
            if (! $cur) {
                $cur = ['start_kind' => $startKind, 'sills' => []];
                $start = $nextStart;
            } else {
                $prev = end($cur['sills']);
                $start = $prev['end'] + $vwidth($k - 1);
            }
            $end = $start + $sill['len'];
            $cur['sills'][] = [
                'bay' => $k, 'pn' => $sill['pn'], 'len' => $sill['len'], 'start' => $start, 'end' => $end,
                'q1' => $start + intdiv($sill['len'], 4), 'q3' => $start + intdiv($sill['len'] * 3, 4),
            ];

            if ($k === $N) {
                $close('right jamb', $rightW + $gapR);

                continue;
            }
            if (isset($doorBays[$k + 1])) {
                continue; // closed by the door branch next iteration
            }

            $mHere = $this->memberHeight($k, $N, $verts, $rightJamb);
            $mNext = $this->memberHeight($k + 1, $N, $verts, $rightJamb);
            if (abs($mHere - $mNext) > $tol) {
                $close('terminating vertical (knee wall)', $vwidth($k) + $gapR);
                $breaks[] = "Knee wall between bay {$k} and bay ".($k + 1).' (vertical '.$this->dec($mHere).'" to '.$this->dec($mNext).'")';
                $nextStart = 0;
                $startKind = 'comp jamb at knee wall';
            }
        }

        // --- receptor pieces
        $pieces = [];
        foreach ($byDesc('SILL RECEPTORS CHANNEL', true) as $r) {
            for ($i = 0; $i < $r['qty']; $i++) {
                $pieces[] = $r['len'];
            }
        }
        [$assigned, $receptorNote] = $this->assignReceptors($runs, $pieces);

        $outRuns = [];
        foreach ($runs as $i => $run) {
            $first = $run['sills'][0]['bay'];
            $lastBay = end($run['sills'])['bay'];
            $label = $first === $lastBay ? "Bay {$first}" : "Bays {$first}-{$lastBay}";
            $receptor = null;
            if (isset($assigned[$i]) && $assigned[$i] !== []) {
                $parts = $assigned[$i];
                $total = array_sum($parts) + $this->units($cfg['receptor_gap']) * (count($parts) - 1);
                $diff = $total - $run['layout_width'];
                $receptor = [
                    'pieces' => array_map(fn ($u) => $this->dec($u), $parts),
                    'receptor_dec' => $this->dec($total), 'layout_dec' => $this->dec($run['layout_width']),
                    'diff_dec' => $this->signed($diff), 'diff_frac' => $this->frac(abs($diff)),
                    'flagged' => abs($diff) > $this->units($cfg['flag_tolerance']),
                ];
            }
            $onReceptor = isset($parts) && $receptor ? $this->layoutOnReceptor($run, $parts) : null;
            unset($parts);
            $outRuns[] = [
                'label' => $label,
                'start_kind' => $run['start_kind'],
                'end_kind' => $run['end_kind'],
                'layout_dec' => $this->dec($run['layout_width']),
                'receptor' => $receptor,
                'on_receptor' => $onReceptor,
                'sills' => array_map(fn ($s) => [
                    'bay' => $s['bay'], 'pn' => $s['pn'],
                    'len' => $this->dec($s['len']), 'len_frac' => $this->frac($s['len']),
                    'start' => $this->dec($s['start']), 'start_frac' => $this->frac($s['start']),
                    'end' => $this->dec($s['end']), 'end_frac' => $this->frac($s['end']),
                    'q1' => $this->dec($s['q1']), 'q1_frac' => $this->frac($s['q1']),
                    'q3' => $this->dec($s['q3']), 'q3_frac' => $this->frac($s['q3']),
                    // raw units for tests/consumers that need exactness
                    'raw' => ['start' => $s['start'], 'end' => $s['end'], 'q1' => $s['q1'], 'q3' => $s['q3']],
                ], $run['sills']),
            ];
        }

        return [
            'project' => $group['project'], 'work_order' => $group['work_order'], 'elevation' => $group['elevation'],
            'left_jamb' => ['pn' => $leftJamb['pn'], 'width' => $this->dec($leftW), 'source' => $leftSrc],
            'right_jamb' => ['pn' => $rightJamb['pn'], 'width' => $this->dec($rightW), 'source' => $rightSrc],
            'vertical_pns' => collect($verts)->pluck('pn')->unique()->values()->all(),
            'breaks' => $breaks,
            'runs' => $outRuns,
            'defaults' => array_values($defaults),
            'receptor_note' => $receptorNote,
        ];
    }

    /**
     * Places each sill's 1/4 and 3/4 points on the receptor piece that physically carries it.
     * Pieces are laid left to right (largest first, receptor_gap between), measured from each piece's
     * own left end and rounded to 1/16"; a mark that falls beyond a piece or inside a gap is reported as off the receptor.
     *
     * @param  int[]  $pieces  unit lengths in layout order
     */
    private function layoutOnReceptor(array $run, array $pieces): array
    {
        $gap = $this->units($this->cfg['receptor_gap']);
        $out = [];
        $offset = 0;
        foreach ($pieces as $i => $len) {
            $out[$i] = ['no' => $i + 1, 'len' => $this->dec($len), 'len_frac' => $this->frac($len), 'offset' => $offset, 'end' => $offset + $len, 'marks' => []];
            $offset += $len + $gap;
        }
        $off = [];
        foreach ($run['sills'] as $sill) {
            foreach (['q1' => '1/4', 'q3' => '3/4'] as $key => $name) {
                $pos = $sill[$key];
                $hit = null;
                foreach ($out as $i => $piece) {
                    if ($pos >= $piece['offset'] && $pos <= $piece['end']) {
                        $hit = $i;
                        break;
                    }
                }
                if ($hit === null) {
                    $off[] = ['bay' => $sill['bay'], 'mark' => $name, 'pos' => $this->dec($pos)];

                    continue;
                }
                // Shop-floor marking: rounded to the nearest 1/16" (half up). Placement above uses the exact value.
                $step = intdiv(self::UNIT, 16);
                $local = intdiv($pos - $out[$hit]['offset'] + intdiv($step, 2), $step) * $step;
                $out[$hit]['marks'][] = ['bay' => $sill['bay'], 'mark' => $name, 'dec' => $this->dec($local), 'frac' => $this->frac($local, 16)];
            }
        }
        foreach ($out as &$piece) {
            unset($piece['offset'], $piece['end']);
        }

        return ['pieces' => array_values($out), 'off' => $off];
    }

    private function memberHeight(int $bay, int $n, array $verts, array $rightJamb): int
    {
        if ($bay === $n) {
            return $rightJamb['len'];
        }

        return ($verts[$bay] ?? throw new ReviewException("Missing FEMALE VERTICAL for bay {$bay}."))['len'];
    }

    /**
     * Assigns receptor pieces to runs by trying every partition and keeping the smallest total
     * |receptor − layout|. Returns [run index => unit lengths, note].
     */
    private function assignReceptors(array $runs, array $pieces): array
    {
        $R = count($runs);
        $P = count($pieces);
        if ($P === 0) {
            return [[], 'no SILL RECEPTORS CHANNEL rows — receptor check not run.'];
        }
        if ($R ** $P > self::MAX_PARTITIONS) {
            return [[], 'too many receptor pieces to match to runs — receptor check not run.'];
        }

        $gap = $this->units($this->cfg['receptor_gap']);
        $best = null;
        $bestAssign = [];
        $assign = array_fill(0, $P, 0);
        $requireAll = $P >= $R;

        $walk = function (int $i) use (&$walk, &$assign, &$best, &$bestAssign, $R, $P, $pieces, $runs, $gap, $requireAll) {
            if ($i === $P) {
                $sum = array_fill(0, $R, 0);
                $cnt = array_fill(0, $R, 0);
                foreach ($assign as $pi => $ri) {
                    $sum[$ri] += $pieces[$pi];
                    $cnt[$ri]++;
                }
                $cost = 0;
                foreach ($runs as $ri => $run) {
                    if ($cnt[$ri] === 0) {
                        if ($requireAll) {
                            return;
                        }

                        continue;
                    }
                    $cost += abs($sum[$ri] + $gap * ($cnt[$ri] - 1) - $run['layout_width']);
                }
                if ($best === null || $cost < $best) {
                    $best = $cost;
                    $bestAssign = $assign;
                }

                return;
            }
            for ($r = 0; $r < $R; $r++) {
                $assign[$i] = $r;
                $walk($i + 1);
            }
        };
        $walk(0);

        $out = [];
        foreach ($bestAssign as $pi => $ri) {
            $out[$ri][] = $pieces[$pi];
        }
        foreach ($out as &$list) {
            rsort($list); // largest piece first, to read naturally
        }

        return [$out, null];
    }

    /** @return array{0: int, 1: string} width in units and where it came from (product|config|default) */
    private function partWidth(string $pn, int|float $fallback): array
    {
        $cacheKey = $pn.'|'.$fallback;
        if (isset($this->widthCache[$cacheKey])) {
            return $this->widthCache[$cacheKey];
        }

        $w = ($this->productWidth)($pn);
        if ($w !== null) {
            return $this->widthCache[$cacheKey] = [$this->units($w), 'product'];
        }
        if (isset($this->cfg['part_widths'][$pn])) {
            return $this->widthCache[$cacheKey] = [$this->units($this->cfg['part_widths'][$pn]), 'config'];
        }

        return $this->widthCache[$cacheKey] = [$this->units($fallback), 'default'];
    }

    // ---------------------------------------------------------------- number formatting

    private function units(int|float|string $inches): int
    {
        return (int) round((float) $inches * self::UNIT);
    }

    /** Decimal inches to 4 places, half-up, from integer units. */
    private function dec(int $u): string
    {
        $neg = $u < 0;
        $t = intdiv(abs($u) + 5_000, 10_000); // ten-thousandths of an inch
        $s = intdiv($t, 10_000).'.'.str_pad((string) ($t % 10_000), 4, '0', STR_PAD_LEFT);

        return ($neg && $t > 0 ? '-' : '').$s;
    }

    private function signed(int $u): string
    {
        $s = $this->dec($u);

        return $u > 0 && $s[0] !== '-' ? '+'.$s : $s;
    }

    /** Nearest 1/denominator, reduced, e.g. 14 25/64. */
    private function frac(int $u, ?int $denominator = null): string
    {
        $neg = $u < 0;
        $u = abs($u);
        $den = $denominator ?? (int) $this->cfg['fraction_denominator'];
        $n = intdiv($u * $den + intdiv(self::UNIT, 2), self::UNIT); // total 1/den units
        $whole = intdiv($n, $den);
        $num = $n % $den;
        if ($num > 0) {
            $g = $this->gcd($num, $den);
            $num = intdiv($num, $g);
            $d = intdiv($den, $g);
        }
        $text = $num === 0 ? (string) $whole : ($whole > 0 ? "{$whole} {$num}/{$d}" : "{$num}/{$d}");

        return ($neg && $n > 0 ? '-' : '').$text.'"';
    }

    private function gcd(int $a, int $b): int
    {
        return $b === 0 ? $a : $this->gcd($b, $a % $b);
    }
}
