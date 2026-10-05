<?php

namespace Tests\Unit;

use App\Services\CutFlow\StickPacker;
use PHPUnit\Framework\TestCase;

class StickPackerTest extends TestCase
{
    public function test_beats_first_fit_decreasing(): void
    {
        // FFD: [4,4] [3,3,3] [3] = 3 sticks. Best: [4,3,3] x2.
        $plan = (new StickPacker)->pack([4, 4, 3, 3, 3, 3], 10, 0);

        $this->assertSame(2, $plan['count']);
        $this->assertTrue($plan['optimal']);
    }

    public function test_kerf_is_only_lost_between_pieces(): void
    {
        $packer = new StickPacker;

        // 5 + 0.5 + 4.5 = 10 exactly fits one 10" stick.
        $this->assertSame(1, $packer->pack([5, 4.5], 10, 0.5)['count']);
        // One more thousandth of kerf and it no longer fits.
        $this->assertSame(2, $packer->pack([5, 4.5], 10, 0.501)['count']);
    }

    public function test_every_piece_is_placed_and_sticks_fit(): void
    {
        mt_srand(7);
        $lengths = [];
        for ($i = 0; $i < 120; $i++) {
            $lengths[] = mt_rand(100, 1200) / 10;
        }
        $kerf = 0.125;

        $plan = (new StickPacker)->pack($lengths, 288, $kerf);

        $placed = array_merge(...$plan['sticks']);
        sort($placed);
        sort($lengths);
        $this->assertEquals($lengths, $placed);

        foreach ($plan['sticks'] as $stick) {
            $this->assertLessThanOrEqual(288 + 1e-9, array_sum($stick) + $kerf * (count($stick) - 1));
        }
        $this->assertGreaterThanOrEqual($plan['lowerBound'], $plan['count']);
    }

    public function test_never_worse_than_first_fit_decreasing_and_deterministic(): void
    {
        mt_srand(11);
        for ($round = 0; $round < 15; $round++) {
            $lengths = [];
            for ($i = 0; $i < 40; $i++) {
                $lengths[] = mt_rand(200, 1400) / 10;
            }
            rsort($lengths);

            $ffd = [];
            foreach ($lengths as $len) {
                foreach ($ffd as $b => $room) {
                    if ($len + 0.125 <= $room) {
                        $ffd[$b] -= $len + 0.125;

                        continue 2;
                    }
                }
                $ffd[] = 288 + 0.125 - $len - 0.125;
            }

            $a = (new StickPacker)->pack($lengths, 288, 0.125);
            $b = (new StickPacker)->pack($lengths, 288, 0.125);

            $this->assertLessThanOrEqual(count($ffd), $a['count']);
            $this->assertSame($a, $b);
        }
    }

    public function test_sticks_are_ordered_by_longest_piece(): void
    {
        $plan = (new StickPacker)->pack([20, 90, 50, 60, 40], 100, 0);

        $firsts = array_column($plan['sticks'], 0);
        $sorted = $firsts;
        rsort($sorted);
        $this->assertSame($sorted, $firsts);
    }

    public function test_empty_pile(): void
    {
        $this->assertSame(['sticks' => [], 'count' => 0, 'lowerBound' => 0, 'optimal' => true], (new StickPacker)->pack([], 288, 0.125));
    }

    public function test_best_fill_uses_a_one_off_stick_as_fully_as_possible(): void
    {
        // Greedy largest-first would take 60 (+ nothing else fits the 40 left except 40... ) — use a case where it loses:
        // 100" offcut, pieces 60, 50, 50: greedy = 60 (waste 40), best = 50+50 (waste 0).
        $fill = (new StickPacker)->bestFill([60, 50, 50], 100, 0);

        $this->assertSame([50.0, 50.0], $fill);
    }

    public function test_leftover_is_concentrated_without_changing_the_stick_count(): void
    {
        mt_srand(21);
        for ($round = 0; $round < 10; $round++) {
            $lengths = [];
            for ($i = 0; $i < 30; $i++) {
                $lengths[] = mt_rand(200, 1400) / 10;
            }
            rsort($lengths);

            // First-fit-decreasing as the baseline.
            $ffd = [];
            foreach ($lengths as $len) {
                foreach ($ffd as $b => $room) {
                    if ($len + 0.125 <= $room) {
                        $ffd[$b] -= $len + 0.125;

                        continue 2;
                    }
                }
                $ffd[] = 288 - $len;
            }

            $plan = (new StickPacker)->pack($lengths, 288, 0.125);
            $leftovers = array_map(fn ($s) => 288 - array_sum($s) - 0.125 * (count($s) - 1), $plan['sticks']);

            if ($plan['count'] === count($ffd)) {
                $this->assertGreaterThanOrEqual(
                    array_sum(array_map(fn ($r) => $r * $r, $ffd)) - 1e-6,
                    array_sum(array_map(fn ($r) => $r * $r, $leftovers))
                );
            }
        }
    }

    public function test_leftover_goes_into_one_long_drop(): void
    {
        // Two sticks are needed (130 of material on 100" sticks); the spare 70 should sit on one stick.
        $plan = (new StickPacker)->pack([45, 45, 10, 10, 10, 10], 100, 0);

        $leftovers = array_map(fn ($s) => 100 - array_sum($s), $plan['sticks']);
        sort($leftovers);
        $this->assertSame([0.0, 70.0], array_map('floatval', $leftovers));
    }
}
