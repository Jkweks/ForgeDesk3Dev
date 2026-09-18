<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Services\ElevationMatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for a real misroute: a report for "Wege Pharmacy"
 * (typed on the PDF as "Wedge Pharmacy") was matching to an unrelated job,
 * "801 Broadway", because that job's elevation tag happened to line up with
 * the report's generic "Door 1" text — even though "Wege Pharmacy" itself
 * was a near-perfect job-name match. Job name must win the job first;
 * elevation tag only breaks ties within that job.
 */
class ElevationMatcherServiceTest extends TestCase
{
    use RefreshDatabase;

    private function elevation(string $jobName, string $tag): FdWoElevation
    {
        static $rel = 0;

        $job = BusinessJob::create([
            'job_number' => 'J'.(++$rel),
            'job_name' => $jobName,
            'status' => 'active',
        ]);

        $wo = FdWorkOrder::create([
            'business_job_id' => $job->id,
            'release_number' => $rel,
            'status' => 'active',
        ]);

        return FdWoElevation::create([
            'work_order_id' => $wo->id,
            'elevation_tag' => $tag,
        ]);
    }

    public function test_job_name_match_wins_over_an_unrelated_jobs_lucky_tag_match(): void
    {
        $correct = $this->elevation('Wege Pharmacy', 'SF-1');
        $decoy = $this->elevation('801 Broadway', 'Door 1');

        $match = (new ElevationMatcherService)->match('Wedge Pharmacy', 'Door 1');

        $this->assertSame($correct->id, $match['elevation_id']);
        $this->assertNotSame($decoy->id, $match['elevation_id']);
    }

    public function test_tag_still_disambiguates_within_the_matched_job(): void
    {
        $job = BusinessJob::create(['job_number' => 'J100', 'job_name' => 'Wege Pharmacy', 'status' => 'active']);
        $wo = FdWorkOrder::create(['business_job_id' => $job->id, 'release_number' => 1, 'status' => 'active']);
        $north = FdWoElevation::create(['work_order_id' => $wo->id, 'elevation_tag' => 'North Door']);
        $south = FdWoElevation::create(['work_order_id' => $wo->id, 'elevation_tag' => 'South Door']);

        $match = (new ElevationMatcherService)->match('Wege Pharmacy', 'South Door');

        $this->assertSame($south->id, $match['elevation_id']);
        $this->assertNotSame($north->id, $match['elevation_id']);
    }

    public function test_falls_back_to_tag_only_when_job_text_is_missing(): void
    {
        $this->elevation('Some Job', 'ZZZ-9');
        $target = $this->elevation('Another Job', 'SF-42');

        $match = (new ElevationMatcherService)->match(null, 'SF-42');

        $this->assertSame($target->id, $match['elevation_id']);
    }
}
