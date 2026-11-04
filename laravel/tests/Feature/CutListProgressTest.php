<?php

namespace Tests\Feature;

use App\Models\CutFlow\CutJob;
use App\Models\CutFlow\CutLogEntry;
use App\Models\CutFlow\Part;
use App\Services\CutFlow\CutListProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CutListProgressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::connection('cutflow')->hasTable('cut_log_entries')) {
            $this->artisan('migrate', ['--database' => 'cutflow', '--path' => 'database/migrations/cutflow', '--force' => true]);
        }
    }

    private function part(CutJob $job, string $name, int $original, int $remaining): Part
    {
        return Part::create([
            'cut_job_id' => $job->id, 'name' => $name, 'finish' => 'BL', 'dimension_inches' => 10,
            'qty_original' => $original, 'qty_remaining' => $remaining,
        ]);
    }

    private function cut(CutJob $job, string $name, string $at, int $operator = 1): void
    {
        $e = CutLogEntry::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'cut_job_id' => $job->id, 'part_name' => $name, 'finish' => 'BL',
            'operator_id' => $operator, 'operator_name' => 'Op', 'dimension_inches' => 10, 'type' => 'planned',
            'is_recut' => false, 'is_reprint' => false, 'is_manual_cut' => false,
        ]);
        $e->forceFill(['created_at' => $at])->save();
    }

    public function test_groups_by_part_number_and_projects_remaining_time(): void
    {
        $job = CutJob::create(['name' => 'J']);
        $this->part($job, 'E100', 10, 4);   // 6 cut, 4 left
        $this->part($job, 'E200', 5, 5);    // untouched

        // Six E100 cuts 30 s apart -> five 30 s gaps; the 1 h break between sessions is ignored.
        foreach ([0, 30, 60, 90, 120, 150] as $s) {
            $this->cut($job, 'E100', now()->subHours(2)->addSeconds($s)->toDateTimeString());
        }

        $r = CutListProgress::forJobs([$job->id]);
        $e100 = $r['by_part']->firstWhere('part_number', 'E100');

        $this->assertSame(6, $e100['done_cuts']);
        $this->assertSame(60.0, $e100['percent']);
        $this->assertSame(30.0, $e100['seconds_per_cut']);
        $this->assertSame(120, $e100['remaining_seconds']);

        // E200 has no history of its own, so it borrows the list's pace: 5 x 30 s.
        $this->assertSame(150, $r['by_part']->firstWhere('part_number', 'E200')['remaining_seconds']);
        $this->assertSame(270, $r['summary']['remaining_seconds']);
        $this->assertSame(6, $r['summary']['done_cuts']);
        $this->assertSame(15, $r['summary']['total_cuts']);
    }

    public function test_no_history_gives_no_estimate(): void
    {
        $job = CutJob::create(['name' => 'J']);
        $this->part($job, 'E100', 3, 3);

        $r = CutListProgress::forJobs([$job->id]);

        $this->assertNull($r['summary']['remaining_seconds']);
        $this->assertSame('—', CutListProgress::duration(null));
    }

    public function test_duration_formatting(): void
    {
        $this->assertSame('45 min', CutListProgress::duration(2700));
        $this->assertSame('2 h 15 min', CutListProgress::duration(8100));
        $this->assertSame('<1 min', CutListProgress::duration(20));
    }
}
