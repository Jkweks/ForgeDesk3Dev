<?php

namespace App\Services;

use App\Models\FdWoElevation;
use Illuminate\Support\Carbon;

/**
 * Guesses which FdWoElevation a quality report belongs to from the free-text
 * job name and elevation tag extracted off the PDF. Always returns a
 * best-guess top candidate (never leaves a report fully unmatched) — the
 * caller decides what to do with a low match_confidence.
 */
class ElevationMatcherService
{
    /** How far past a work order's newest elevation completion to still consider it a candidate. */
    private const RECENTLY_COMPLETED_DAYS = 45;

    /**
     * @return array{elevation_id: ?int, work_order_id: ?int, confidence: ?float, candidates: array}
     */
    public function match(?string $jobText, ?string $elevationTagText, ?string $reportDate = null): array
    {
        $candidates = $this->candidatePool();

        // The report's "Elevation / Door Opening" field frequently just says
        // "Door" or "Door 3" rather than naming an elevation tag — when it
        // does, narrow the pool to Door-type elevations first so a door
        // opening can't get matched against an unrelated SF/CW elevation
        // that merely happens to share more characters. Falls back to the
        // full pool if that leaves nothing to match against.
        if ($elevationTagText !== null && str_contains(mb_strtolower($elevationTagText), 'door')) {
            $doorCandidates = $candidates->filter(
                fn (FdWoElevation $elevation) => in_array('door', $elevation->elevationType?->matchTerms() ?? [], true)
            );
            if ($doorCandidates->isNotEmpty()) {
                $candidates = $doorCandidates->values();
            }
        }

        if ($candidates->isEmpty()) {
            return ['elevation_id' => null, 'work_order_id' => null, 'confidence' => null, 'candidates' => []];
        }

        // Phase 1 — find the job(s) whose name best matches jobText, and
        // restrict the pool to their elevations before ever looking at tag
        // text. Job name is the primary identifying signal; scoring tag
        // similarity across every job first (the old approach) let an
        // unrelated job's lucky tag match (tags are often generic, e.g.
        // "Door 1") outscore a job whose *name* was actually the much
        // better match — e.g. a report for "Wege Pharmacy" landing on "801
        // Broadway" because that job's elevation tag happened to line up,
        // even though "Wege Pharmacy" itself was a near-perfect job-name
        // match. Ties — including "no job text at all", where every job
        // scores 0 — merge into one combined pool, which naturally falls
        // back to ranking by tag alone across every candidate.
        $jobGroups = $candidates->groupBy(
            fn (FdWoElevation $e) => $e->workOrder?->business_job_id ?? 'wo-'.$e->work_order_id
        );

        $jobScores = $jobGroups->map(
            fn ($group) => $this->similarity($jobText, $group->first()->workOrder?->businessJob?->job_name)
        );
        $bestJobScore = $jobScores->max();
        $bestJobKeys = $jobScores->filter(fn ($score) => abs($score - $bestJobScore) < 0.0001)->keys();
        // Not ->only()->collapse(): Eloquent\Collection overrides only() to
        // filter by model primary key (getKey()), which blows up here since
        // these keys are group keys, not model IDs.
        $pool = $bestJobKeys->reduce(fn ($carry, $key) => $carry->concat($jobGroups->get($key)), collect());

        // Phase 2 — within the matched job(s), rank by elevation-tag similarity.
        $scored = $pool->map(function (FdWoElevation $elevation) use ($bestJobScore, $elevationTagText, $reportDate) {
            $tagScore = $this->similarity($elevationTagText, $elevation->elevation_tag);
            $score = ($bestJobScore * 0.5) + ($tagScore * 0.5);

            if ($reportDate) {
                $anchor = $elevation->date_completed ?? $elevation->date_requested;
                if ($anchor) {
                    $days = abs(Carbon::parse($reportDate)->diffInDays($anchor));
                    $score += max(0, 10 - $days) * 0.2; // small tiebreak boost for close dates
                }
            }

            return [
                'elevation_id' => $elevation->id,
                'work_order_id' => $elevation->work_order_id,
                'elevation_tag' => $elevation->elevation_tag,
                'work_order_label' => $elevation->workOrder?->releaseLabel(),
                'score' => round(min($score, 100), 2),
            ];
        })->sortByDesc('score')->values();

        $top = $scored->first();

        return [
            'elevation_id' => $top['elevation_id'],
            'work_order_id' => $top['work_order_id'],
            'confidence' => $top['score'],
            'candidates' => $scored->take(5)->all(),
        ];
    }

    private function candidatePool()
    {
        return FdWoElevation::query()
            ->with(['workOrder.businessJob', 'elevationType'])
            ->whereHas('workOrder', function ($q) {
                $q->whereIn('status', ['active', 'on_hold'])
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'complete')
                            ->where('completed_at', '>=', now()->subDays(self::RECENTLY_COMPLETED_DAYS));
                    });
            })
            ->get();
    }

    /** Full compass words a job/elevation name might spell out, mapped to the abbreviation a work order might use instead (e.g. "Howell south west" vs "Howell SW"). */
    private const DIRECTION_WORDS = [
        'northeast' => 'ne', 'northwest' => 'nw', 'southeast' => 'se', 'southwest' => 'sw',
        'north' => 'n', 'south' => 's', 'east' => 'e', 'west' => 'w',
    ];

    private function similarity(?string $a, ?string $b): float
    {
        $a = trim((string) $a);
        $b = trim((string) $b);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        return max(
            $this->rawSimilarity($a, $b),
            $this->rawSimilarity($this->normalizeDirections($a), $this->normalizeDirections($b)),
        );
    }

    private function rawSimilarity(string $a, string $b): float
    {
        $a = mb_strtolower($a);
        $b = mb_strtolower($b);

        if ($a === $b) {
            return 100.0;
        }
        if (str_contains($b, $a) || str_contains($a, $b)) {
            return 70.0;
        }

        similar_text($a, $b, $percent);

        return round($percent, 2);
    }

    /**
     * Spells-out-to-abbreviation normalization: "south west" / "southwest"
     * and "SW" both collapse to the same "sw" token, so either form of a
     * job name matches the other. Non-direction words are left untouched.
     */
    private function normalizeDirections(string $s): string
    {
        $tokens = preg_split('/\s+/', mb_strtolower(trim($s)));

        $mapped = array_map(fn ($t) => self::DIRECTION_WORDS[$t] ?? $t, $tokens);

        $collapsed = [];
        foreach ($mapped as $token) {
            $isDirectionCode = strlen($token) <= 2 && preg_match('/^[nsew]+$/', $token);
            $prev = $collapsed === [] ? null : $collapsed[array_key_last($collapsed)];
            if ($isDirectionCode && $prev !== null && strlen($prev) <= 2 && preg_match('/^[nsew]+$/', $prev)) {
                $collapsed[array_key_last($collapsed)] .= $token;
            } else {
                $collapsed[] = $token;
            }
        }

        return implode(' ', $collapsed);
    }
}
