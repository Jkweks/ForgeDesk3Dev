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
}
