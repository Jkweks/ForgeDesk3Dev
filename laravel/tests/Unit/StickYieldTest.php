<?php

namespace Tests\Unit;

use App\Services\Configurator\StickYield;
use PHPUnit\Framework\TestCase;

class StickYieldTest extends TestCase
{
    public function test_old_optimizer_is_first_fit_decreasing(): void
    {
        $this->assertSame(3, StickYield::sticks([4, 4, 3, 3, 3, 3], 10, false));
    }

    public function test_new_optimizer_finds_the_fewer_stick_plan(): void
    {
        $this->assertSame(2, StickYield::sticks([4, 4, 3, 3, 3, 3], 10, true));
    }

    public function test_a_cut_longer_than_the_stick_still_costs_one_stick_either_way(): void
    {
        $this->assertSame(2, StickYield::sticks([12, 5, 5], 10, false));
        $this->assertSame(2, StickYield::sticks([12, 5, 5], 10, true));
    }

    public function test_new_optimizer_is_never_worse_on_random_piles(): void
    {
        mt_srand(5);
        for ($round = 0; $round < 10; $round++) {
            $cuts = [];
            for ($i = 0; $i < 30; $i++) {
                $cuts[] = mt_rand(100, 1300) / 10;
            }

            $this->assertLessThanOrEqual(StickYield::sticks($cuts, 252, false), StickYield::sticks($cuts, 252, true));
        }
    }

    public function test_no_cuts_need_no_sticks(): void
    {
        $this->assertSame(0, StickYield::sticks([], 252, true));
        $this->assertSame(0, StickYield::sticks([], 252, false));
    }
}
