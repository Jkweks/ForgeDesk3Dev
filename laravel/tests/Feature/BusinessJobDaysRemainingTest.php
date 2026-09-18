<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * days_until_completion is whole days, measured from the start of today, so the
 * "Days Remaining" column never shows a long decimal.
 */
class BusinessJobDaysRemainingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_future_target_returns_whole_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-09 15:47:12'));

        $job = BusinessJob::create([
            'job_number' => 'J1', 'job_name' => 'J', 'status' => 'active',
            'target_completion_date' => '2026-09-22',
        ]);

        $this->assertSame(13, $job->days_until_completion);
    }

    public function test_overdue_target_is_negative_whole_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-09 01:00:00'));

        $job = BusinessJob::create([
            'job_number' => 'J2', 'job_name' => 'J', 'status' => 'active',
            'target_completion_date' => '2026-09-04',
        ]);

        $this->assertSame(-5, $job->days_until_completion);
    }

    public function test_null_when_no_target_or_completed(): void
    {
        $job = BusinessJob::create([
            'job_number' => 'J3', 'job_name' => 'J', 'status' => 'active',
        ]);

        $this->assertNull($job->days_until_completion);
    }
}
