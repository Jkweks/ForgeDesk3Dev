<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\DrawingSet;
use App\Models\PmFile;
use App\Models\User;
use App\Services\Pm\PmFileClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** F: share ingest: watcher webhook, classifier, inbox, scan (docs/plans/pm-suite-integration.md 4a). */
class FdriveIngestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.fdrive_ingest.token' => 'ingest-secret', 'services.pm_worker.token' => 'tok', 'services.pm_worker.url' => 'http://worker.test']);
    }

    private function ingest(array $body, ?string $token = 'ingest-secret')
    {
        return $this->postJson('/api/v1/ingest/fdrive', $body, $token ? ['X-Ingest-Token' => $token] : []);
    }

    private function job(string $number = '2301'): BusinessJob
    {
        return BusinessJob::withoutGlobalScopes()->create(['job_number' => $number, 'job_name' => 'Wege', 'status' => 'won']);
    }

    private function event(string $path, string $content = 'x', array $over = []): array
    {
        return array_merge(['path' => $path, 'size' => strlen($content), 'sha256' => hash('sha256', $content), 'mtime' => '2026-11-10T10:00:00Z', 'event' => 'created'], $over);
    }

    public function test_classifier_reads_type_and_job_from_the_path(): void
    {
        $job = $this->job();
        $c = app(PmFileClassifier::class);

        $r = $c->classify('F:\\Engineering\\Jobs In PROGRESS\\2301 Wege Pharmacy\\Estimate\\BidEstimate_Wege.xls');
        $this->assertSame(['bid_estimate', '2301', $job->id], [$r['doc_type'], $r['job_number'], $r['job']->id]);

        $this->assertSame('drawing_set', $c->classify('F:\\Jobs\\2301 Wege\\Shop Drawings\\set.pdf')['doc_type']);
        $this->assertSame('unclassified', $c->classify('F:\\Jobs\\2301 Wege\\notes.txt')['doc_type']);
        $this->assertNull($c->classify('F:\\Misc\\random.pdf')['job_number']);
        $this->assertNull($c->classify('F:\\Jobs\\9999 Nope\\BidEstimate_x.xls')['job']);
    }

    public function test_webhook_needs_the_token(): void
    {
        $this->ingest($this->event('F:\\a.xls'), null)->assertStatus(401);
        $this->ingest($this->event('F:\\a.xls'), 'wrong')->assertStatus(401);
        config(['services.fdrive_ingest.token' => null]);
        $this->ingest($this->event('F:\\a.xls'), 'anything')->assertStatus(401);
    }

    public function test_bid_estimate_with_a_known_job_is_imported_once(): void
    {
        $job = $this->job();
        Http::fake(['worker.test/estimate/parse*' => Http::response(['parserRevision' => 550, 'summary' => ['contractAmount' => 100], 'lines' => [], 'reviewNeeded' => []])]);
        // the worker cannot read F: itself: Laravel reads the file from the mount, so use a real temp file
        $path = sys_get_temp_dir().'/2301 Wege/BidEstimate_Wege.xlsx';
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, 'PK-workbook');

        $ev = ['path' => $path, 'size' => strlen('PK-workbook'), 'sha256' => hash('sha256', 'PK-workbook'), 'mtime' => '2026-11-10T10:00:00Z'];
        $this->ingest($ev)->assertOk()->assertJsonPath('action', 'imported');
        $this->assertSame('imported', PmFile::firstOrFail()->status);
        $this->assertSame($job->id, PmFile::first()->business_job_id);
        $this->assertSame(1, \App\Models\BidEstimate::count());

        // same path, size and mtime: nothing happens, no hashing, no worker call
        Http::fake();
        $this->ingest($ev)->assertOk()->assertJsonPath('action', 'unchanged');
        Http::assertNothingSent();

        // moved/renamed: same content is recognised, path updated, not imported again
        $this->ingest(['path' => $path.'.moved', 'size' => $ev['size'], 'sha256' => $ev['sha256'], 'mtime' => '2026-11-11T10:00:00Z'])->assertOk()->assertJsonPath('action', 'known');
        $this->assertSame(1, PmFile::count());
        $this->assertSame($path.'.moved', PmFile::first()->fdrive_path);
        @unlink($path);
    }

    public function test_drawing_sets_are_queued_and_unplaceable_files_go_to_the_inbox(): void
    {
        $this->job();
        Http::fake(['worker.test/drawing/parse' => Http::response(['ok' => true, 'pageCount' => 1, 'elevations' => [], 'warnings' => []])]);

        $this->ingest($this->event('F:\\Jobs\\2301 Wege\\Shop Drawings\\set.pdf', 'set-bytes'))->assertOk()->assertJsonPath('action', 'queued');
        // drawing_set import needs the file bytes; an F: path that is not mounted here fails that read and is recorded, not lost
        $this->assertSame(1, DrawingSet::count());

        $this->ingest($this->event('F:\\Jobs\\2301 Wege\\notes.txt', 'n'))->assertOk()->assertJsonPath('action', 'review');
        $this->ingest($this->event('F:\\Elsewhere\\BidEstimate_Q.xls', 'q'))->assertOk()->assertJsonPath('action', 'review');
        $this->ingest($this->event('F:\\Jobs\\9999 Nope\\BidEstimate_Z.xls', 'z'))->assertOk()->assertJsonPath('action', 'review');
        $this->assertStringContainsString('No job 9999', PmFile::where('original_name', 'BidEstimate_Z.xls')->first()->error);

        $this->ingest(['path' => 'F:\\no\\hash.xls', 'size' => 5])->assertOk()->assertJsonPath('action', 'needs_hash');
        $this->ingest(['path' => 'F:\\gone.xls', 'size' => 5, 'event' => 'deleted'])->assertOk()->assertJsonPath('action', 'ignored');
    }

    public function test_inbox_assign_files_the_document_and_runs_the_import(): void
    {
        $job = $this->job('2302');
        $this->ingest($this->event('F:\\Elsewhere\\BidEstimate_Q.xls', 'q-bytes'))->assertOk();
        $file = PmFile::firstOrFail();
        // content must be readable for the import: store it like an upload would
        Storage::disk('local')->put('pm_files/x.xls', 'q-bytes');
        $file->update(['storage_path' => 'pm_files/x.xls']);
        Http::fake(['worker.test/estimate/parse*' => Http::response(['parserRevision' => 550, 'summary' => [], 'lines' => [], 'reviewNeeded' => []])]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->assertCount(1, $this->getJson('/api/v1/pm/inbox')->assertOk()->json());

        $this->postJson("/api/v1/pm/inbox/{$file->id}/assign", ['business_job_id' => $job->id, 'doc_type' => 'bid_estimate'])
            ->assertOk()->assertJsonPath('action', 'imported');
        $this->assertCount(0, $this->getJson('/api/v1/pm/inbox')->json());
        $this->assertSame(1, \App\Models\BidEstimate::where('business_job_id', $job->id)->count());

        $this->postJson("/api/v1/pm/inbox/{$file->id}/assign", ['business_job_id' => $job->id, 'doc_type' => 'bogus'])->assertStatus(422);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]))->get('/pm/inbox')->assertOk()->assertSee('File Inbox');
    }

    public function test_scan_command_walks_a_root_through_the_same_ingest(): void
    {
        $this->job('2303');
        $dir = sys_get_temp_dir().'/scan-'.uniqid().'/2303 Scan';
        mkdir($dir, 0777, true);
        file_put_contents("$dir/readme.txt", 'hello');

        $this->artisan('pm:scan-fdrive', ['--root' => [dirname($dir)]])->expectsOutputToContain('Scanned 1 files')->assertSuccessful();
        $this->assertSame('review', PmFile::firstOrFail()->status);
        $this->artisan('pm:scan-fdrive', ['--root' => [dirname($dir)]])->expectsOutputToContain('"unchanged":1')->assertSuccessful();
        $this->assertSame(1, PmFile::count());

        $this->artisan('pm:scan-fdrive')->expectsOutputToContain('No roots configured')->assertSuccessful();
        unlink("$dir/readme.txt");
    }
}
