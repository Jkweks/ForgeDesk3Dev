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

        if ($candidates->isEmpty()) {
            return ['elevation_id' => null, 'work_order_id' => null, 'confidence' => null, 'candidates' => []];
        }

        $scored = $candidates->map(function (FdWoElevation $elevation) use ($jobText, $elevationTagText, $reportDate) {
            $jobScore = $this->similarity($jobText, $elevation->workOrder?->businessJob?->job_name);
            $tagScore = $this->similarity($elevationTagText, $elevation->elevation_tag);
            $score = ($jobScore * 0.4) + ($tagScore * 0.6);

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
            ->with(['workOrder.businessJob'])
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
