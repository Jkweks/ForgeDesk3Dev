<?php

namespace Tests\Feature;

use App\Models\BidEstimate;
use App\Models\BusinessJob;
use App\Models\CostCode;
use App\Models\PmFile;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Pm\PmFileIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** BidEstimate import through the pm-worker (docs/plans/pm-suite-integration.md). */
class BidEstimateImportTest extends TestCase
{
    use RefreshDatabase;

    private BusinessJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pm_worker.token' => 'tok', 'services.pm_worker.url' => 'http://worker.test']);
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->job = BusinessJob::withoutGlobalScopes()->create(['job_number' => '9300', 'job_name' => 'Bid', 'status' => 'bidding', 'origin' => 'pm']);
    }

    private function parsed(): array
    {
        $line = fn ($row, $code, $total, $extra = []) => array_merge([
            'section' => 'material', 'row' => $row, 'who' => 'Splendor $', 'costCode' => $code,
            'description' => "Line {$row}", 'qty' => 2, 'unitPrice' => $total / 2, 'total' => $total, 'confidence' => 'parsed',
        ], $extra);

        return [
            'parserRevision' => 550, 'sheet' => 'CONTRACT', 'meta' => ['gc' => 'ACME'],
            'summary' => ['contractAmount' => 12500.5],
            'lines' => [$line(10, 200, 1000), $line(11, 200, 500), $line(12, 9999, 300, ['reviewReason' => 'code_not_on_master_list'])],
            'adjustments' => [], 'warnings' => [],
            'reviewNeeded' => [
                $line(12, 9999, 300, ['reviewReason' => 'code_not_on_master_list']),
                $line(40, null, 75, ['reviewReason' => 'no_cost_code_found', 'confidence' => 'missing']),
            ],
            'lineCount' => 3, 'reviewCount' => 2,
        ];
    }

    private function fakeWorker(): void
    {
        Http::fake([
            'worker.test/estimate/sheets' => Http::response(['sheets' => ['CONTRACT', 'CO #1']]),
            'worker.test/estimate/parse*' => Http::response($this->parsed()),
        ]);
    }

    private function upload(string $content = 'PK-workbook-bytes')
    {
        return $this->postJson("/api/v1/pm/jobs/{$this->job->id}/estimates/upload", [
            'file' => UploadedFile::fake()->createWithContent('BidEstimate_9300.xlsx', $content),
        ]);
    }

    public function test_seeded_cost_codes_exist(): void
    {
        $this->assertGreaterThan(80, CostCode::count());
        $this->assertSame('Fab - Shop', CostCode::find(100)->description);
    }

    public function test_upload_registers_file_lists_sheets_and_dedupes_by_hash(): void
    {
        $this->fakeWorker();

        $first = $this->upload()->assertCreated()->assertJsonPath('already_uploaded', false)->assertJsonPath('sheets.1', 'CO #1');
        $second = $this->upload()->assertOk()->assertJsonPath('already_uploaded', true);

        $this->assertSame($first->json('file.id'), $second->json('file.id'));
        $this->assertSame(1, PmFile::count());
        $this->assertSame(hash('sha256', 'PK-workbook-bytes'), PmFile::first()->sha256);
    }

    public function test_import_stores_estimate_and_skips_unchanged_reimports(): void
    {
        $this->fakeWorker();
        $fileId = $this->upload()->json('file.id');
        $url = "/api/v1/pm/jobs/{$this->job->id}/estimates/import";

        $this->postJson($url, ['pm_file_id' => $fileId, 'sheet' => 'CONTRACT'])->assertOk()->assertJsonPath('outcome', 'imported');
        $this->assertSame(1, BidEstimate::count());
        $est = BidEstimate::first();
        $this->assertEquals(12500.5, $est->contract_amount);
        $this->assertSame('ACME', $est->gc);
        $this->assertSame(4, $est->lines()->count()); // 3 usable + 1 codeless review row, no duplicates
        $this->assertSame(2, $est->lines()->whereNotNull('review_reason')->count());

        Http::fake(); // any further worker call would now return an empty 200
        $this->postJson($url, ['pm_file_id' => $fileId, 'sheet' => 'CONTRACT'])->assertOk()->assertJsonPath('outcome', 'unchanged');
        Http::assertNothingSent(); // no wasted parse

        // A changed file for the same sheet re-imports in place
        $this->fakeWorker();
        $newId = $this->upload('PK-edited-bytes')->json('file.id');
        $this->postJson($url, ['pm_file_id' => $newId, 'sheet' => 'CONTRACT'])->assertOk()->assertJsonPath('outcome', 'reimported');
        $this->assertSame(1, BidEstimate::count());
    }

    public function test_overrides_fix_and_ignore_rows_and_survive_reimport(): void
    {
        $this->fakeWorker();
        $fileId = $this->upload()->json('file.id');
        $import = "/api/v1/pm/jobs/{$this->job->id}/estimates/import";
        $this->postJson($import, ['pm_file_id' => $fileId, 'sheet' => 'CONTRACT'])->assertOk();
        $base = "/api/v1/pm/jobs/{$this->job->id}/estimates/CONTRACT";

        // fix the off-list code on row 12, code the uncoded row 40, ignore nothing yet
        $this->postJson("$base/override", ['section' => 'material', 'row' => 12, 'action' => 'fix', 'cost_code' => 205])->assertOk();
        $res = $this->postJson("$base/override", ['section' => 'material', 'row' => 40, 'action' => 'fix', 'cost_code' => 100])->assertOk();

        $this->assertSame(0, $res->json('reviewCount'));
        $this->assertSame(4, $res->json('lineCount')); // row 40 joined the usable lines
        $this->assertEquals(1500, $res->json('byCostCode.200.total'));
        $this->assertEquals(300, $res->json('byCostCode.205.total'));
        $this->assertSame('Fab - Shop', $res->json('byCostCode.100.description'));

        $res = $this->postJson("$base/override", ['section' => 'material', 'row' => 11, 'action' => 'ignore'])->assertOk();
        $this->assertEquals(1000, $res->json('byCostCode.200.total'));

        // codes must exist on the master list
        $this->postJson("$base/override", ['section' => 'material', 'row' => 10, 'action' => 'fix', 'cost_code' => 4242])->assertStatus(422);

        // a forced re-import keeps the PM's decisions
        $this->fakeWorker();
        $res = $this->postJson($import, ['pm_file_id' => $fileId, 'sheet' => 'CONTRACT', 'force' => true])->assertOk();
        $this->assertEquals(1000, $res->json('estimate.byCostCode.200.total'));

        $this->deleteJson("$base/override/material/11")->assertOk()->assertJsonPath('byCostCode.200.total', 1500);
    }

    public function test_same_sheet_row_in_two_sections_does_not_collide(): void
    {
        $p = $this->parsed();
        $p['lines'][] = ['section' => 'labor', 'row' => 10, 'who' => null, 'costCode' => 100, 'description' => 'Shop labor',
            'qty' => 8, 'unitPrice' => 50, 'total' => 400, 'confidence' => 'parsed'];
        Http::fake([
            'worker.test/estimate/sheets' => Http::response(['sheets' => ['CONTRACT']]),
            'worker.test/estimate/parse*' => Http::response($p),
        ]);
        $fileId = $this->upload()->json('file.id');
        $res = $this->postJson("/api/v1/pm/jobs/{$this->job->id}/estimates/import", ['pm_file_id' => $fileId, 'sheet' => 'CONTRACT'])->assertOk();
        $this->assertSame(1000.0, (float) $res->json('estimate.byCostCode.200.total') - 500); // material row 10
        $this->assertEquals(400, $res->json('estimate.byCostCode.100.total'));              // labor row 10 kept

        // ignoring the material row 10 leaves the labor row 10 alone
        $res = $this->postJson("/api/v1/pm/jobs/{$this->job->id}/estimates/CONTRACT/override", ['section' => 'material', 'row' => 10, 'action' => 'ignore'])->assertOk();
        $this->assertEquals(400, $res->json('byCostCode.100.total'));
        $this->assertEquals(500, $res->json('byCostCode.200.total'));
    }

    public function test_worker_problems_are_reported_cleanly(): void
    {
        Http::fake(['worker.test/*' => Http::response(['error' => 'No SUPPLIER header found'], 422)]);
        $this->upload()->assertStatus(422)->assertJsonPath('message', 'No SUPPLIER header found');

        config(['services.pm_worker.token' => null]);
        $this->upload('other-bytes')->assertStatus(503);
    }

    public function test_requires_pm_permission_and_works_for_pm_only_jobs(): void
    {
        $this->fakeWorker();
        // PM-only (bidding) job is reachable here although Fabrication cannot see it
        $this->assertSame(0, BusinessJob::where('job_number', '9300')->count());
        $this->upload()->assertCreated();

        \App\Models\Role::firstOrCreate(['name' => 'shopper'], ['display_name' => 'Shopper']);
        Sanctum::actingAs(User::factory()->create(['role' => 'shopper', 'is_active' => true]), ['*']);
        $this->getJson("/api/v1/pm/jobs/{$this->job->id}/estimates")->assertForbidden();
    }

    public function test_watcher_entry_point_registers_without_copying_and_recognises_moves(): void
    {
        $ing = app(PmFileIngestor::class);
        $sha = hash('sha256', 'x');

        [$f, $known] = $ing->registerFromFdrive('F:\\Engineering\\9300\\BidEstimate_9300.xls', 'bid_estimate', $this->job, $sha, 1);
        $this->assertFalse($known);
        $this->assertSame('fdrive', $f->source);
        $this->assertNull($f->storage_path);

        [$again, $known] = $ing->registerFromFdrive('F:\\Engineering\\9300\\Renamed.xls', 'bid_estimate', $this->job, $sha, 1);
        $this->assertTrue($known);
        $this->assertSame($f->id, $again->id);
        $this->assertSame('F:\\Engineering\\9300\\Renamed.xls', $again->fresh()->fdrive_path);
    }

    public function test_job_page_show_endpoint_and_decisions_list(): void
    {
        $this->get("/pm/jobs/{$this->job->id}")->assertOk()->assertSee('Upload BidEstimate workbook');
        $this->getJson("/api/v1/pm/jobs/{$this->job->id}")->assertOk()->assertJsonPath('job_number', '9300')->assertJsonPath('status_label', 'Bidding');
        $this->getJson('/api/v1/pm/jobs/999999')->assertNotFound();

        $this->fakeWorker();
        $fileId = $this->upload()->json('file.id');
        $this->postJson("/api/v1/pm/jobs/{$this->job->id}/estimates/import", ['pm_file_id' => $fileId, 'sheet' => 'CONTRACT'])->assertOk();
        $res = $this->postJson("/api/v1/pm/jobs/{$this->job->id}/estimates/CONTRACT/override", ['section' => 'material', 'row' => 11, 'action' => 'ignore'])->assertOk();

        $this->assertEquals([['section' => 'material', 'row' => 11, 'action' => 'ignore', 'costCode' => null, 'description' => 'Line 11', 'total' => 500.0]], $res->json('decisions'));
    }
}
