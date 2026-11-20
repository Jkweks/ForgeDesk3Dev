<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\CutFlow\CutJob;
use App\Models\CutFlow\Part;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Models\FdWoStage;
use App\Services\CutFlow\CutStepSync;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CutStepSyncTest extends TestCase
{
    use RefreshDatabase;

    private FdWorkOrder $wo;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::connection('cutflow')->hasTable('cut_log_entries')) {
            $this->artisan('migrate', ['--database' => 'cutflow', '--path' => 'database/migrations/cutflow', '--force' => true]);
        }
        $job = BusinessJob::create(['job_number' => '2001', 'job_name' => 'Plaza', 'status' => 'active']);
        $this->wo = FdWorkOrder::create(['business_job_id' => $job->id, 'release_number' => 2]);
    }

    private function elev(string $tag): FdWoElevation
    {
        $e = FdWoElevation::create(['work_order_id' => $this->wo->id, 'elevation_tag' => $tag, 'quantity' => 1]);
        FdWoStage::create(['elevation_id' => $e->id, 'work_order_id' => $this->wo->id, 'name' => 'Cut', 'sort_order' => 1, 'status' => 'pending']);
        FdWoStage::create(['elevation_id' => $e->id, 'work_order_id' => $this->wo->id, 'name' => 'Assemble', 'sort_order' => 2, 'status' => 'pending']);

        return $e;
    }

    private function cutStep(FdWoElevation $e): FdWoStage
    {
        return FdWoStage::where('elevation_id', $e->id)->where('name', 'Cut')->firstOrFail();
    }

    private function part(CutJob $job, string $phase, int $qty = 2, string $name = 'E100'): Part
    {
        return Part::create(['cut_job_id' => $job->id, 'name' => $name, 'dimension_inches' => 10, 'qty_original' => $qty, 'qty_remaining' => $qty, 'phase' => $phase]);
    }

    private function cut(Part $p, int $n = 1): void
    {
        $p->decrement('qty_remaining', $n);
        CutStepSync::afterPartCut($p->fresh());
    }

    public function test_first_cut_starts_the_elevation_and_the_last_completes_it(): void
    {
        $a = $this->elev('E1');
        $b = $this->elev('E2');
        $job = CutJob::create(['name' => 'wo', 'work_order_id' => $this->wo->id]);
        $a1 = $this->part($job, 'E1');
        $a2 = $this->part($job, 'E1', 1, 'E200');
        $this->part($job, 'E2');

        $this->assertSame('pending', $this->cutStep($a)->status);
        $this->cut($a1);
        $this->assertSame('in_progress', $this->cutStep($a)->status);
        $this->assertNotNull($this->cutStep($a)->started_at);
        $this->assertSame('pending', $this->cutStep($b)->status, 'other elevations untouched');

        $this->cut($a1);   // a1 done, a2 still open
        $this->assertSame('in_progress', $this->cutStep($a)->status);
        $this->cut($a2);
        $this->assertSame('complete', $this->cutStep($a)->status);
        $this->assertNotNull($this->cutStep($a)->completed_at);
        $this->assertSame('pending', $this->cutStep($b)->status);
        // other steps are never touched
        $this->assertSame('pending', FdWoStage::where('elevation_id', $a->id)->where('name', 'Assemble')->first()->status);
    }

    public function test_uploaded_tags_match_ignoring_case_and_spacing_and_multi_tag_phases(): void
    {
        $a = $this->elev('11/E9');
        $b = $this->elev('E2');
        $c = $this->elev('E3');
        $job = CutJob::create(['name' => 'upload', 'work_order_id' => $this->wo->id]);
        $p = $this->part($job, ' 11 /e9 ', 1);
        $multi = $this->part($job, 'e2 / E3', 1);

        $this->cut($p);
        $this->assertSame('complete', $this->cutStep($a)->status);

        $this->cut($multi);
        $this->assertSame('complete', $this->cutStep($b)->status);
        $this->assertSame('complete', $this->cutStep($c)->status);
    }

    public function test_unlinked_upload_is_tied_to_its_work_order_by_name(): void
    {
        $a = $this->elev('E1');
        // plain CSV upload: no work_order_id, named "<job number>-<release token>" (release 2 => R2)
        $job = CutJob::create(['name' => '2001-R2']);
        $p = $this->part($job, 'E1', 1);

        $this->cut($p);
        $this->assertSame('complete', $this->cutStep($a)->status);

        // an unknown name links to nothing and does nothing
        $b = $this->elev('E9');
        $other = CutJob::create(['name' => 'Some other upload']);
        $this->cut($this->part($other, 'E9', 1));
        $this->assertSame('pending', $this->cutStep($b)->status);
    }

    public function test_held_steps_are_not_auto_started_and_finished_ones_and_unmatched_phases_are_ignored(): void
    {
        $a = $this->elev('E1');
        $b = $this->elev('E2');
        $job = CutJob::create(['name' => 'wo', 'work_order_id' => $this->wo->id]);
        $this->cutStep($a)->update(['status' => 'on_hold']);
        $this->cutStep($b)->update(['status' => 'not_required']);

        $p = $this->part($job, 'E1', 2);
        $this->cut($p);                                   // started, not finished: the hold stays
        $this->cut($this->part($job, 'E2', 1));
        $this->cut($this->part($job, 'NOPE', 1));

        $this->assertSame('on_hold', $this->cutStep($a)->status);
        $this->assertSame('not_required', $this->cutStep($b)->status);

        $this->cut($p);                                   // everything cut: it is done whatever the hold said
        $this->assertSame('complete', $this->cutStep($a)->status);
    }

    // ---- manual links ----

    private function asUser(): void
    {
        $this->actingAs(User::factory()->create());
        $this->withoutMiddleware(\App\Http\Middleware\CheckPermission::class);
    }

    public function test_a_phase_that_matches_nothing_can_be_linked_by_hand_and_cutting_already_done_counts(): void
    {
        $this->asUser();
        $a = $this->elev('E1');
        $job = CutJob::create(['name' => 'uploaded', 'work_order_id' => $this->wo->id]);
        $p = $this->part($job, 'Storefront A', 2);

        $this->cut($p, 2);   // all cut, but "Storefront A" matches no elevation yet
        $this->assertSame('pending', $this->cutStep($a)->status);

        $r = $this->getJson("/api/v1/cut-lists/{$job->id}/elevation-links")->assertOk();
        $this->assertFalse($r->json('phases.0.linked'));
        $this->assertSame([$a->id], collect($r->json('elevations'))->pluck('id')->all());

        $r = $this->putJson("/api/v1/cut-lists/{$job->id}/elevation-links", ['phase' => 'Storefront A', 'elevation_ids' => [$a->id]])->assertOk();
        $this->assertTrue($r->json('phases.0.linked'));
        $this->assertSame($a->id, $r->json('phases.0.manual.0.id'));
        $this->assertSame('complete', $this->cutStep($a)->status, 'finished cutting is picked up when the link is made');

        // clearing the links un-links the phase (the Cut step that already completed is not reopened)
        $r = $this->putJson("/api/v1/cut-lists/{$job->id}/elevation-links", ['phase' => 'Storefront A', 'elevation_ids' => []])->assertOk();
        $this->assertFalse($r->json('phases.0.linked'));
    }

    public function test_manual_links_start_the_step_on_the_next_cut_and_reject_foreign_elevations(): void
    {
        $this->asUser();
        $a = $this->elev('E1');
        $job = CutJob::create(['name' => 'uploaded', 'work_order_id' => $this->wo->id]);
        $p = $this->part($job, 'Frame 7', 3);
        $this->putJson("/api/v1/cut-lists/{$job->id}/elevation-links", ['phase' => 'frame 7', 'elevation_ids' => [$a->id]])->assertOk();

        $this->cut($p);
        $this->assertSame('in_progress', $this->cutStep($a)->status);

        $otherWo = FdWorkOrder::create(['business_job_id' => $this->wo->business_job_id, 'release_number' => 9]);
        $foreign = FdWoElevation::create(['work_order_id' => $otherWo->id, 'elevation_tag' => 'X', 'quantity' => 1]);
        $this->putJson("/api/v1/cut-lists/{$job->id}/elevation-links", ['phase' => 'Frame 7', 'elevation_ids' => [$foreign->id]])->assertStatus(422);
        $this->putJson("/api/v1/cut-lists/{$job->id}/elevation-links", ['phase' => 'No such phase', 'elevation_ids' => []])->assertStatus(422);
    }

    public function test_an_unlinked_list_can_be_linked_to_a_work_order_by_hand(): void
    {
        $this->asUser();
        $a = $this->elev('E1');
        $job = CutJob::create(['name' => 'Totally different name']);
        $p = $this->part($job, 'E1', 1);
        $this->cut($p);
        $this->assertSame('pending', $this->cutStep($a)->status);

        $this->putJson("/api/v1/cut-lists/{$job->id}/elevation-links", ['phase' => 'E1', 'elevation_ids' => []])->assertStatus(422); // no work order yet

        $r = $this->putJson("/api/v1/cut-lists/{$job->id}/work-order", ['work_order_id' => $this->wo->id])->assertOk();
        $this->assertSame($this->wo->id, $r->json('work_order.id'));
        $this->assertFalse($r->json('work_order.by_name'));
        $this->assertSame('complete', $this->cutStep($a)->status, 'tag matched on its own once the list belongs to the work order');

        // a second list for the same work order becomes an add-on
        $second = CutJob::create(['name' => 'More']);
        $this->putJson("/api/v1/cut-lists/{$second->id}/work-order", ['work_order_id' => $this->wo->id])->assertOk();
        $this->assertSame(2, $second->fresh()->sequence);

        $this->putJson("/api/v1/cut-lists/{$job->id}/work-order", ['work_order_id' => null])->assertOk()->assertJsonPath('work_order', null);
    }
}
