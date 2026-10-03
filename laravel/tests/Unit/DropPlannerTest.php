<?php

namespace Tests\Unit;

use App\Services\CutFlow\DropPlanner;
use PHPUnit\Framework\TestCase;

class DropPlannerTest extends TestCase
{
    private function plan(float $leftover): array
    {
        // Min Drop 72, Min Split 120, Max Drop 144, no kerf
        return (new DropPlanner)->plan($leftover, 72, 120, 144, 0);
    }

    public function test_drop_is_racked_and_tagged_down_to_nearest_five(): void
    {
        $this->assertSame([['type' => 'rack', 'length' => 89.0, 'tag' => 85.0, 'cut_at' => null]], $this->plan(89));
        $this->assertSame(135.0, $this->plan(139)[0]['tag']);
    }

    public function test_too_short_is_scrap(): void
    {
        $this->assertSame('scrap', $this->plan(71)[0]['type']);
        $this->assertSame('rack', $this->plan(72)[0]['type']);
    }

    public function test_oversized_drop_is_split_at_min_split(): void
    {
        $segments = $this->plan(229);

        $this->assertSame(['rack', 'rack'], array_column($segments, 'type'));
        $this->assertSame([120.0, 109.0], array_column($segments, 'length'));
        $this->assertSame([120.0, 105.0], array_column($segments, 'tag'));
    }

    public function test_split_shifts_when_it_would_leave_a_stub(): void
    {
        $segments = $this->plan(150);

        $this->assertSame([78.0, 72.0], array_column($segments, 'length'));
    }

    public function test_unsplittable_oversize_stays_whole(): void
    {
        $segments = (new DropPlanner)->plan(145, 72, 120, 144, 0);

        $this->assertCount(2, $segments);
        $this->assertSame([73.0, 72.0], array_column($segments, 'length'));
    }

    public function test_kerf_is_taken_off_the_split(): void
    {
        $segments = (new DropPlanner)->plan(229, 72, 120, 144, 0.125);

        $this->assertSame(108.875, $segments[1]['length']);
        $this->assertSame(105.0, $segments[1]['tag']);
    }
}
