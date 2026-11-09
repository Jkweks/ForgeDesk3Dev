<?php

namespace Tests\Feature;

use App\Models\CutFlow\CutJob;
use App\Models\CutFlow\CutLogEntry;
use App\Models\CutFlow\Part;
use App\Models\FdUser;
use App\Models\User;
use App\Services\CutFlow\CutStationStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CutListMarkCutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::connection('cutflow')->hasTable('cut_log_entries')) {
            $this->artisan('migrate', ['--database' => 'cutflow', '--path' => 'database/migrations/cutflow', '--force' => true]);
        }
    }

    public function test_marks_pieces_cut_by_user_on_date_and_counts_in_totals(): void
    {
        $user = User::factory()->create();
        $this->withoutMiddleware(\App\Http\Middleware\CheckPermission::class);
        $cutter = FdUser::create(['name' => 'Sam Saw', 'active' => true]);
        $job = CutJob::create(['name' => 'Other saw job']);
        $part = Part::create(['cut_job_id' => $job->id, 'name' => 'E100', 'finish' => 'BL', 'dimension_inches' => 10, 'qty_original' => 5, 'qty_remaining' => 5]);

        $this->actingAs($user)->postJson("/api/v1/cut-lists/{$job->id}/mark-cut", [
            'operator_id' => $cutter->id, 'cut_date' => '2026-11-02', 'parts' => [['part_id' => $part->id, 'qty' => 3]],
        ])->assertOk()->assertJson(['count' => 3]);

        $this->assertSame(2, $part->fresh()->qty_remaining);
        $entries = CutLogEntry::where('cut_job_id', $job->id)->get();
        $this->assertCount(3, $entries);
        $this->assertTrue($entries->every(fn ($e) => $e->is_external && $e->operator_id === $cutter->id && $e->operator_name === 'Sam Saw' && $e->cut_at_local->toDateString() === '2026-11-02'));
        $this->assertSame(3, CutStationStats::cuts()->count());

        $this->actingAs($user)->postJson("/api/v1/cut-lists/{$job->id}/mark-cut", [
            'operator_id' => $cutter->id, 'cut_date' => '2026-11-02', 'parts' => [['part_id' => $part->id, 'qty' => 3]],
        ])->assertStatus(422);
    }

    public function test_omitting_parts_marks_everything_remaining(): void
    {
        $user = User::factory()->create();
        $this->withoutMiddleware(\App\Http\Middleware\CheckPermission::class);
        $cutter = FdUser::create(['name' => 'Sam Saw', 'active' => true]);
        $job = CutJob::create(['name' => 'All']);
        Part::create(['cut_job_id' => $job->id, 'name' => 'E1', 'dimension_inches' => 10, 'qty_original' => 2, 'qty_remaining' => 2]);
        Part::create(['cut_job_id' => $job->id, 'name' => 'E2', 'dimension_inches' => 20, 'qty_original' => 1, 'qty_remaining' => 1]);

        $this->actingAs($user)->postJson("/api/v1/cut-lists/{$job->id}/mark-cut", ['operator_id' => $cutter->id, 'cut_date' => '2026-11-02'])
            ->assertOk()->assertJson(['count' => 3]);
        $this->assertSame(0, (int) Part::where('cut_job_id', $job->id)->sum('qty_remaining'));
    }
}
