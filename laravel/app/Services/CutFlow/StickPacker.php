<?php

namespace App\Services\CutFlow;

/**
 * The "New Optimizer": packs one profile's pieces onto as few sticks as it can.
 *
 * Kerf is only lost between two pieces, so a stick holds its pieces when
 * sum(len) + (n - 1) * kerf <= stock, i.e. sum(len + kerf) <= stock + kerf. Each
 * piece is therefore sized len + kerf against a capacity of stock + kerf, and
 * all maths is done in integer thousandths of an inch.
 *
 * Seeds with first-fit-decreasing, then searches for a plan one stick shorter
 * (depth-first, bounded by a node count rather than a clock so the same pile
 * always gives the same answer) until it reaches the lower bound, proves the
 * count can't drop, or runs out of budget.
 *
 * Among plans with that stick count it then shifts pieces between sticks so the
 * leftover is concentrated: tight sticks plus one long drop, rather than several
 * short offcuts that would all be scrapped.
 */
class StickPacker
{
    public const DEFAULT_NODE_LIMIT = 200000;

    private int $nodes = 0;

    private int $nodeLimit = 0;

    /**
     * @param  array<int, float>  $lengths  One entry per piece (inches). Pieces longer than the stock
     *                                      length must be filtered out by the caller.
     * @return array{sticks: array<int, array<int, float>>, count: int, lowerBound: int, optimal: bool}
     *                                                                                                  Sticks hold piece lengths, longest piece first; sticks ordered by their longest piece.
     */
    public function pack(array $lengths, float $stockLength, float $kerf, int $nodeLimit = self::DEFAULT_NODE_LIMIT): array
    {
        if (empty($lengths)) {
            return ['sticks' => [], 'count' => 0, 'lowerBound' => 0, 'optimal' => true];
        }

        $capacity = (int) round(($stockLength + $kerf) * 1000);
        $items = [];
        foreach ($lengths as $length) {
            $items[] = ['len' => (float) $length, 'size' => (int) round(((float) $length + $kerf) * 1000)];
        }
        usort($items, fn ($a, $b) => $b['size'] <=> $a['size']);
        $sizes = array_column($items, 'size');

        $lowerBound = $this->lowerBound($sizes, $capacity);
        $best = $this->firstFitDecreasing($sizes, $capacity);
        $optimal = count($best) <= $lowerBound;

        $this->nodes = 0;
        $this->nodeLimit = $nodeLimit;

        while (! $optimal) {
            $target = count($best) - 1;
            $found = $this->search($sizes, $capacity, $target);

            if ($found === null) {
                break; // budget spent: keep the best plan so far, not proven minimal
            }
            if ($found === false) {
                $optimal = true; // proved no plan with one fewer stick exists

                break;
            }

            $best = $found;
            $optimal = count($best) <= $lowerBound;
        }

        $best = $this->concentrateLeftover($best, $sizes, $capacity);

        // Map item indexes back to lengths.
        $sticks = array_map(function (array $indexes) use ($items) {
            $lens = array_map(fn ($i) => $items[$i]['len'], $indexes);
            rsort($lens);

            return $lens;
        }, $best);
        usort($sticks, fn ($a, $b) => $b[0] <=> $a[0] ?: count($b) <=> count($a));

        return ['sticks' => $sticks, 'count' => count($sticks), 'lowerBound' => $lowerBound, 'optimal' => $optimal];
    }

    /**
     * The subset of pieces that fills one stick of the given length as full as possible
     * (used for a one-off stick such as an offcut, where "fill it up" is the right goal).
     *
     * @param  array<int, float>  $lengths
     * @return array<int, float> chosen piece lengths, longest first
     */
    public function bestFill(array $lengths, float $stockLength, float $kerf, int $stateLimit = 100000): array
    {
        $capacity = (int) round(($stockLength + $kerf) * 1000);
        $items = [];
        foreach ($lengths as $length) {
            $size = (int) round(((float) $length + $kerf) * 1000);
            if ($size <= $capacity) {
                $items[] = ['len' => (float) $length, 'size' => $size];
            }
        }
        usort($items, fn ($a, $b) => $b['size'] <=> $a['size']);

        // reachable sum => [previous sum, item index that reached it]
        $reach = [0 => [null, null]];
        foreach ($items as $i => $item) {
            foreach (array_keys($reach) as $sum) {
                $next = $sum + $item['size'];
                if ($next <= $capacity && ! isset($reach[$next]) && count($reach) < $stateLimit) {
                    $reach[$next] = [$sum, $i];
                }
            }
        }

        $sum = max(array_keys($reach));
        $picked = [];
        while ($sum > 0) {
            [$prev, $index] = $reach[$sum];
            $picked[] = $items[$index]['len'];
            $sum = $prev;
        }
        rsort($picked);

        return $picked;
    }

    /**
     * Hill-climb on the sum of squared leftovers (higher = waste gathered into fewer, longer
     * offcuts) using single moves and pairwise swaps. Every step strictly raises an integer
     * objective, so it terminates; the stick count can only stay the same or drop.
     *
     * @param  array<int, array<int, int>>  $bins  bins of item indexes
     * @param  array<int, int>  $sizes
     * @return array<int, array<int, int>>
     */
    private function concentrateLeftover(array $bins, array $sizes, int $capacity): array
    {
        $bins = array_values($bins);
        $room = array_map(fn (array $bin) => $capacity - array_sum(array_map(fn ($i) => $sizes[$i], $bin)), $bins);
        $count = count($bins);

        for ($pass = 0; $pass < 1000; $pass++) {
            $improved = false;

            for ($a = 0; $a < $count && ! $improved; $a++) {
                for ($b = 0; $b < $count && ! $improved; $b++) {
                    if ($a === $b) {
                        continue;
                    }

                    foreach ($bins[$a] as $ia => $itemA) {
                        $sa = $sizes[$itemA];

                        // Move A's piece into B when that leaves A with more room than B had.
                        if ($sa <= $room[$b] && $room[$b] < $room[$a] + $sa) {
                            $bins[$b][] = $itemA;
                            unset($bins[$a][$ia]);
                            $room[$b] -= $sa;
                            $room[$a] += $sa;
                            $improved = true;
                            break;
                        }

                        // Swap with a smaller piece in B so A ends up with more room.
                        foreach ($bins[$b] as $ib => $itemB) {
                            $d = $sa - $sizes[$itemB];
                            if ($d > 0 && $d <= $room[$b] && $room[$b] < $room[$a] + $d) {
                                $bins[$a][$ia] = $itemB;
                                $bins[$b][$ib] = $itemA;
                                $room[$a] += $d;
                                $room[$b] -= $d;
                                $improved = true;
                                break 2;
                            }
                        }
                    }
                }
            }

            if (! $improved) {
                break;
            }
        }

        return array_values(array_filter(array_map('array_values', $bins)));
    }

    /**
     * @param  array<int, int>  $sizes  descending
     */
    private function lowerBound(array $sizes, int $capacity): int
    {
        $bound = (int) ceil(array_sum($sizes) / $capacity);
        $overHalf = count(array_filter($sizes, fn ($s) => $s * 2 > $capacity));

        return max($bound, $overHalf);
    }

    /**
     * @param  array<int, int>  $sizes  descending
     * @return array<int, array<int, int>> bins of item indexes
     */
    private function firstFitDecreasing(array $sizes, int $capacity): array
    {
        $bins = [];
        $remaining = [];

        foreach ($sizes as $i => $size) {
            $placed = false;
            foreach ($remaining as $b => $room) {
                if ($size <= $room) {
                    $bins[$b][] = $i;
                    $remaining[$b] -= $size;
                    $placed = true;
                    break;
                }
            }
            if (! $placed) {
                $bins[] = [$i];
                $remaining[] = $capacity - $size;
            }
        }

        return $bins;
    }

    /**
     * Depth-first search for a packing into exactly $bins sticks.
     *
     * @param  array<int, int>  $sizes  descending
     * @return array<int, array<int, int>>|false|null a plan, false if none exists, null if the node budget ran out
     */
    private function search(array $sizes, int $capacity, int $bins): array|false|null
    {
        $n = count($sizes);
        $allowedWaste = $bins * $capacity - array_sum($sizes);
        if ($allowedWaste < 0) {
            return false;
        }

        $remaining = array_fill(0, $bins, $capacity);
        $assign = array_fill(0, $n, 0);
        $smallest = $sizes[$n - 1];

        $result = $this->place(0, $sizes, $remaining, $assign, $smallest, $allowedWaste, 0);

        if ($result === null) {
            return null;
        }
        if ($result === false) {
            return false;
        }

        $out = array_fill(0, $bins, []);
        foreach ($assign as $item => $bin) {
            $out[$bin][] = $item;
        }

        return array_values(array_filter($out));
    }

    /**
     * @param  array<int, int>  $remaining
     * @param  array<int, int>  $assign
     */
    private function place(int $i, array $sizes, array &$remaining, array &$assign, int $smallest, int $allowedWaste, int $startBin): ?bool
    {
        $n = count($sizes);
        if ($i === $n) {
            return true;
        }

        if (++$this->nodes > $this->nodeLimit) {
            return null;
        }

        $size = $sizes[$i];
        $tried = [];
        $from = ($i > 0 && $sizes[$i - 1] === $size) ? $startBin : 0; // identical pieces: keep bin order

        for ($b = $from, $count = count($remaining); $b < $count; $b++) {
            $room = $remaining[$b];
            if ($size > $room || isset($tried[$room])) {
                continue; // doesn't fit / same space as a bin already tried (symmetry)
            }
            $tried[$room] = true;

            $remaining[$b] -= $size;
            $assign[$i] = $b;

            // Space in sticks that can no longer take even the smallest piece is dead waste.
            $dead = 0;
            foreach ($remaining as $r) {
                if ($r < $smallest) {
                    $dead += $r;
                }
            }

            if ($dead <= $allowedWaste) {
                $result = $this->place($i + 1, $sizes, $remaining, $assign, $smallest, $allowedWaste, $b);
                if ($result !== false) {
                    if ($result === true) {
                        return true;
                    }
                    $remaining[$b] += $size;

                    return null;
                }
            }

            $remaining[$b] += $size;
        }

        return false;
    }
}
