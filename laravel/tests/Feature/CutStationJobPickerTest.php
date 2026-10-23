<?php

namespace Tests\Feature;

use App\Livewire\CutFlow\Dashboard;
use App\Models\CutFlow\CutJob;
use App\Models\CutFlow\CutLogEntry;
use App\Models\CutFlow\Part;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class CutStationJobPickerTest extends TestCase
{
    use RefreshDatabase;

    private CutJob $pending;

    private CutJob $inProgress;

    private CutJob $doneA;

    private CutJob $doneB;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::connection('cutflow')->hasTable('cut_log_entries')) {
            $this->artisan('migrate', ['--database' => 'cutflow', '--path' => 'database/migrations/cutflow', '--force' => true]);
        }

        config(['services.tiger_bridge.fake' => true]);

        $this->pending = $this->job('Pending Job', 4, 4);
        $this->inProgress = $this->job('Partial Job', 4, 2);
        $this->doneA = $this->job('Done Job A', 4, 0);
        $this->doneB = $this->job('Done Job B', 2, 0);
    }

    private function job(string $name, int $original, int $remaining): CutJob
    {
        $job = CutJob::create(['name' => $name]);
        Part::create(['cut_job_id' => $job->id, 'name' => 'Rail', 'dimension_inches' => 48, 'qty_original' => $original, 'qty_remaining' => $remaining]);

        return $job;
    }

    private function station(array $selected = [])
    {
        session([
            'cutflow_crew' => [['key' => 'k1', 'operator_id' => 7, 'name' => 'Ann']],
            'cutflow_active_crew_key' => 'k1',
            'cutflow_active_job_ids' => $selected,
        ]);

        return Livewire::test(Dashboard::class);
    }

    public function test_completed_job_ids_need_parts_and_nothing_left_to_cut(): void
    {
        CutJob::create(['name' => 'Empty list']);

        $this->assertEqualsCanonicalizing([$this->doneA->id, $this->doneB->id], CutJob::completedIds());
    }

    public function test_picker_hides_completed_lists_until_asked(): void
    {
        $page = $this->station([$this->pending->id])->call('openJobsModal');

        $names = fn () => $page->viewData('jobs')->pluck('job_title')->all();
        $this->assertEqualsCanonicalizing(['Pending Job', 'Partial Job'], $names());

        $page->call('toggleShowCompletedJobs');
        $this->assertCount(4, $names());

        // Closing and reopening the picker goes back to hiding them.
        $page->call('closeModal')->call('openJobsModal');
        $this->assertCount(2, $names());
    }

    public function test_default_selection_is_an_open_list_not_a_completed_one(): void
    {
        $page = $this->station([]);

        $this->assertSame([$this->pending->id], $page->get('activeJobIds'));
    }

    public function test_open_lists_stack_but_completed_lists_are_one_at_a_time(): void
    {
        $page = $this->station([$this->pending->id])
            ->call('toggleJob', $this->inProgress->id);
        $this->assertEqualsCanonicalizing([$this->pending->id, $this->inProgress->id], $page->get('activeJobIds'));

        // A completed list replaces the open ones...
        $page->call('toggleJob', $this->doneA->id);
        $this->assertSame([$this->doneA->id], $page->get('activeJobIds'));

        // ...a second completed list replaces the first...
        $page->call('toggleJob', $this->doneB->id);
        $this->assertSame([$this->doneB->id], $page->get('activeJobIds'));

        // ...and picking an open list drops the completed one.
        $page->call('toggleJob', $this->pending->id);
        $this->assertSame([$this->pending->id], $page->get('activeJobIds'));
    }

    public function test_select_all_only_takes_open_lists(): void
    {
        $page = $this->station([$this->doneA->id])
            ->call('openJobsModal')->call('toggleShowCompletedJobs')->call('selectVisibleJobs');

        $this->assertEqualsCanonicalizing([$this->pending->id, $this->inProgress->id], $page->get('activeJobIds'));
    }

    public function test_a_mixed_selection_left_in_the_session_keeps_only_open_lists(): void
    {
        $page = $this->station([$this->doneA->id, $this->doneB->id, $this->pending->id]);
        $this->assertSame([$this->pending->id], $page->get('activeJobIds'));

        $page = $this->station([$this->doneA->id, $this->doneB->id]);
        $this->assertSame([$this->doneA->id], $page->get('activeJobIds'));
    }

    public function test_completed_list_shows_its_finished_profiles_and_pieces(): void
    {
        $open = $this->station([$this->pending->id]);
        $this->assertSame(1, $open->viewData('profiles')->count());
        $this->assertFalse($open->viewData('viewingCompleted'));

        $page = $this->station([$this->doneA->id]);
        $this->assertTrue($page->viewData('viewingCompleted'));
        $this->assertSame(['Rail'], $page->viewData('profiles')->pluck('name')->all());
        $this->assertSame('Rail', $page->get('activeProfileName'));
        $this->assertSame(1, $page->viewData('parts')->count());
    }

    public function test_recut_part_returns_one_piece_to_the_pool_and_logs_it(): void
    {
        $part = $this->doneA->parts()->first();
        $this->station([$this->doneA->id])->call('recutPart', $part->id);

        $this->assertSame(1, $part->fresh()->qty_remaining);
        $this->assertSame(0, \App\Services\CutFlow\CutStationStats::cuts()->count());

        $log = CutLogEntry::sole();
        $this->assertTrue($log->is_recut);
        $this->assertSame([7, 'Ann', $this->doneA->id], [$log->operator_id, $log->operator_name, $log->cut_job_id]);
        $this->assertSame(1, \App\Services\CutFlow\CutStationStats::recuts()->count());

        // The list is no longer completed, so it now behaves like any open one.
        $this->assertNotContains($this->doneA->id, CutJob::completedIds());
    }

    public function test_recut_part_refuses_pieces_never_cut_and_lists_that_are_not_selected(): void
    {
        $untouched = $this->pending->parts()->first();
        $this->station([$this->pending->id])->call('recutPart', $untouched->id);
        $this->assertSame(4, $untouched->fresh()->qty_remaining);

        $other = $this->doneB->parts()->first();
        $this->station([$this->doneA->id])->call('recutPart', $other->id);
        $this->assertSame(0, $other->fresh()->qty_remaining);

        $this->assertSame(0, CutLogEntry::count());
    }
}
