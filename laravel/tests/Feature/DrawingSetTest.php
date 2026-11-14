<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\DrawingSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Drawing import through the worker (docs/plans/pm-suite-integration.md, feature #21). */
class DrawingSetTest extends TestCase
{
    use RefreshDatabase;

    private BusinessJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pm_worker.token' => 'tok', 'services.pm_worker.url' => 'http://worker.test']);
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->job = BusinessJob::withoutGlobalScopes()->create(['job_number' => '1701', 'job_name' => 'D', 'status' => 'active']);
    }

    private function upload(string $content = '%PDF-1 set')
    {
        return $this->post("/api/v1/pm/jobs/{$this->job->id}/drawing-sets", ['file' => UploadedFile::fake()->createWithContent('shop.pdf', $content)], ['Accept' => 'application/json']);
    }

    private function parsed(): array
    {
        return ['ok' => true, 'pageCount' => 40, 'skippedPages' => 38, 'setFinish' => ['finish' => 'CLEAR ANODIZED'], 'warnings' => ['Page 9 unreadable'],
            'elevations' => [[
                'page' => 12, 'label' => 'W1', 'archRef' => 'A-401', 'system' => 'TUBELITE T14000', 'finish' => 'CLEAR', 'finishCode' => 'C2',
                'thusReqd' => 2, 'oppReqd' => 1, 'totalReqd' => 3, 'roW' => 120.5, 'fsW' => 119.75, 'roH' => 96.0, 'fsH' => 95.5,
                'doW' => null, 'doH' => null, 'dloWidths' => [38.25, 40.0], 'dloHeights' => [90.5], 'glassTags' => ['IG-1', 'IG-1', 'IG-2'],
            ]]];
    }

    public function test_upload_queues_import_and_stores_elevations_relationally(): void
    {
        Http::fake(['worker.test/drawing/parse' => Http::response($this->parsed())]);

        $this->upload()->assertStatus(202);   // QUEUE_CONNECTION=sync in tests: the job has already run

        $set = DrawingSet::firstOrFail();
        $this->assertSame('done', $set->status);
        $this->assertSame(40, $set->page_count);
        $this->assertSame('CLEAR ANODIZED', $set->set_finish);

        $show = $this->getJson("/api/v1/pm/jobs/{$this->job->id}/drawing-sets/{$set->id}")->assertOk();
        $e = $show->json('elevations.0');
        $this->assertSame('W1', $e['label']);
        $this->assertSame('A-401', $e['arch_ref']);
        $this->assertEquals(120.5, $e['ro_w']);
        $this->assertSame([38.25, 40.0], array_map('floatval', array_column(array_filter($e['dims'], fn ($d) => $d['axis'] === 'w'), 'value')));
        $this->assertSame(['IG-1', 'IG-2'], array_column($e['glass_tags'], 'tag'));
        $this->assertSame('Page 9 unreadable', $show->json('warnings.0.message'));

        $this->assertSame(1, $this->getJson("/api/v1/pm/jobs/{$this->job->id}/drawing-sets")->json('0.elevations_count'));
    }

    public function test_same_file_is_not_queued_twice_and_reimport_replaces_results(): void
    {
        Http::fake(['worker.test/drawing/parse' => Http::response($this->parsed())]);
        $this->upload()->assertStatus(202);
        $this->upload()->assertOk()->assertJsonPath('already_uploaded', true);
        $this->assertSame(1, DrawingSet::count());
        Http::assertSentCount(1);

        $set = DrawingSet::first();
        $this->postJson("/api/v1/pm/jobs/{$this->job->id}/drawing-sets/{$set->id}/reimport")->assertStatus(202);
        $this->assertSame(1, $set->elevations()->count());   // replaced, not duplicated
        Http::assertSentCount(2);
    }

    public function test_worker_failures_and_unreadable_files_are_recorded(): void
    {
        Http::fake(['worker.test/drawing/parse' => Http::response(['ok' => false, 'error' => 'Could not open this file as a PDF: bad'])]);
        $this->upload('%PDF-bad')->assertStatus(202);
        $this->assertSame('failed', DrawingSet::first()->status);
        $this->assertStringContainsString('Could not open', DrawingSet::first()->error);

        Http::fake(['worker.test/drawing/parse' => Http::response('boom', 500)]);
        $this->upload('%PDF-other')->assertStatus(202);
        $this->assertSame('failed', DrawingSet::orderByDesc('id')->first()->status);

        $this->post("/api/v1/pm/jobs/{$this->job->id}/drawing-sets", ['file' => UploadedFile::fake()->createWithContent('x.docx', 'x')], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_scope(): void
    {
        Http::fake(['worker.test/drawing/parse' => Http::response($this->parsed())]);
        $this->upload()->assertStatus(202);
        Sanctum::actingAs(User::factory()->create(['role' => 'project_manager', 'is_active' => true]), ['*']);
        $this->getJson("/api/v1/pm/jobs/{$this->job->id}/drawing-sets")->assertNotFound();
    }

    public function test_job_page_has_the_drawings_card(): void
    {
        $this->get("/pm/jobs/{$this->job->id}")->assertOk()->assertSee('Shop drawings');
    }

    public function test_real_parser_shapes_glass_tag_dicts_and_storage_errors_never_leave_it_running(): void
    {
        $p = $this->parsed();
        // the real parser returns each glass tag as an event with its page position
        $p['elevations'][0]['glassTags'] = [['tag' => 'IG-1', 'x0' => 10.5, 'top' => 20], ['tag' => 'IG-1', 'x0' => 90, 'top' => 20], ['tag' => 'IG-2', 'x0' => 50, 'top' => 70]];
        $p['elevations'][0]['finish'] = ['text' => 'CLEAR'];
        Http::fake(['worker.test/drawing/parse' => Http::response($p)]);
        $this->upload()->assertStatus(202);
        $set = DrawingSet::firstOrFail();
        $this->assertSame('done', $set->status);
        $this->assertSame(['IG-1', 'IG-2'], $set->elevations()->first()->glassTags()->pluck('tag')->all());

        // anything that goes wrong while storing must fail the set with a message, not leave it "running"
        Http::fake(['worker.test/drawing/parse' => Http::response($this->parsed())]);
        \App\Models\DrawingElevation::creating(fn () => throw new \RuntimeException('boom'));
        $this->upload('%PDF-2 other')->assertStatus(202);
        $failed = DrawingSet::orderByDesc('id')->first();
        $this->assertSame('failed', $failed->status);
        $this->assertStringContainsString('Could not store the results: boom', $failed->error);
        \App\Models\DrawingElevation::flushEventListeners();
    }

    private function parsedWithMarks(): array
    {
        $p = $this->parsed();
        $p['elevations'][0]['pageSize'] = [1224, 792];
        $p['elevations'][0]['titleRect'] = [395.5, 579.0, 696.1, 628.3];
        $p['elevations'][0]['dims'] = [
            'roW' => null, 'fsW' => ['v' => 119.75, 'rect' => [100, 50, 140, 62]], 'roH' => ['v' => 96.0, 'rect' => [842.2, 331.6, 855.3, 361.8]],
            'fsH' => null, 'doW' => null, 'doH' => null,
            'dloWidths' => [['v' => 38.25, 'rect' => [146, 215, 191, 228]], ['v' => 40.0, 'rect' => [200, 215, 242, 228]]],
            'dloHeights' => [['v' => 90.5, 'rect' => [900, 300, 912, 340]]],
        ];
        // a second sheet of the same elevation (multi-sheet runs keep each sheet's own marks)
        $p['elevations'][0]['sheetDims'] = [
            ['page' => 12, 'pageSize' => [1224, 792], 'titleRect' => [395.5, 579.0, 696.1, 628.3], 'dims' => $p['elevations'][0]['dims']],
            ['page' => 13, 'pageSize' => [1224, 792], 'titleRect' => null, 'dims' => ['dloWidths' => [['v' => 33.75, 'rect' => [10, 20, 50, 33]]]]],
        ];

        return $p;
    }

    public function test_dimension_footprints_are_stored_per_sheet_and_status_follows_confirmation(): void
    {
        Http::fake(['worker.test/drawing/parse' => Http::response($this->parsedWithMarks())]);
        $this->upload()->assertStatus(202);
        $set = DrawingSet::firstOrFail();
        $e = $this->getJson("/api/v1/pm/jobs/{$this->job->id}/drawing-sets/{$set->id}")->assertOk()->json('elevations.0');

        $this->assertSame([12, 13], array_column($e['sheets'], 'page'));
        $this->assertEquals(1224, $e['sheets'][0]['width']);
        $marks = collect($e['marks']);
        $this->assertSame(6, $marks->count());                                   // fs_w, ro_h, 2 dlo_w, 1 dlo_h on page 12, 1 dlo_w on page 13
        $this->assertSame([13], $marks->where('page', 13)->pluck('page')->unique()->values()->all());
        $this->assertSame(['suggested'], $marks->pluck('status')->unique()->values()->all());
        $roH = $marks->firstWhere('kind', 'ro_h');
        $this->assertEquals(842.2, $roH['x0']);

        $url = "/api/v1/pm/jobs/{$this->job->id}/drawing-sets/{$set->id}/marks";
        $dlo = $marks->where('kind', 'dlo_w')->where('page', 12)->pluck('id')->all();
        // confirm both lites, then correct one of them
        $r = $this->postJson($url, ['ids' => $dlo, 'action' => 'confirm'])->assertOk();
        $this->assertSame(['confirmed'], collect($r->json())->pluck('status')->unique()->all());
        $r = $this->postJson($url, ['ids' => [$dlo[0]], 'action' => 'correct', 'value' => 38.5])->assertOk();
        $this->assertSame('mismatch', $r->json('0.status'));
        $this->assertEquals(38.5, $r->json('0.confirmed_value'));
        $this->postJson($url, ['ids' => $dlo, 'action' => 'correct', 'value' => 1])->assertStatus(422);   // one at a time
        $this->postJson($url, ['ids' => [$dlo[0]], 'action' => 'clear'])->assertOk()->assertJsonPath('0.status', 'suggested');
        $this->postJson($url, ['ids' => [999999], 'action' => 'confirm'])->assertNotFound();
        $this->postJson($url, ['ids' => [$dlo[0]], 'action' => 'correct'])->assertStatus(422);            // value required

        // a re-read keeps the stored data consistent (marks replaced with the elevation, not duplicated)
        $this->postJson("/api/v1/pm/jobs/{$this->job->id}/drawing-sets/{$set->id}/reimport")->assertStatus(202);
        $this->assertSame(6, \App\Models\DrawingDimMark::count());
    }

    public function test_sheet_image_is_rendered_by_the_worker_once_and_then_served_from_storage(): void
    {
        Http::fake([
            'worker.test/drawing/parse' => Http::response($this->parsedWithMarks()),
            'worker.test/pdf/page-image*' => Http::response("\x89PNG\r\n\x1a\nfake-image-bytes", 200, ['Content-Type' => 'image/png']),
        ]);
        $this->upload()->assertStatus(202);
        $set = DrawingSet::firstOrFail();
        $img = "/api/v1/pm/jobs/{$this->job->id}/drawing-sets/{$set->id}/pages/12/image";

        $this->get($img)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get($img)->assertOk();
        Http::assertSentCount(2);                                               // parse + one render; the second view came from storage

        $this->get("/api/v1/pm/jobs/{$this->job->id}/drawing-sets/{$set->id}/pages/99/image")->assertNotFound();   // not a sheet of this set
        Sanctum::actingAs(User::factory()->create(['role' => 'project_manager', 'is_active' => true]), ['*']);
        $this->get($img)->assertNotFound();                                       // outside their job scope
    }
}
