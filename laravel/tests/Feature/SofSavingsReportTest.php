<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\JobDocument;
use App\Models\JobReservation;
use App\Models\JobReservationItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\EzEstimateSummaryReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SofSavingsReportTest extends TestCase
{
    use RefreshDatabase;

    /** A workbook with just the Summary sheet cells the reader looks at. */
    private function estimate(string $path, mixed $net, float $freight = 10, float $grand = 0, float $list = 0): void
    {
        $book = new Spreadsheet;
        $ws = $book->getActiveSheet()->setTitle('Summary');
        $ws->setCellValue('F92', $net)->setCellValue('F95', $freight)->setCellValue('F107', $grand ?: (is_numeric($net) ? $net + $freight : 0))->setCellValue('F111', $list);
        (new Xlsx($book))->save(Storage::disk('local')->path($path));
    }

    private function jobWithUpload(string $number, mixed $net, float $reservationCost, bool $archived = false): JobDocument
    {
        $sup = Supplier::firstOrCreate(['name' => 'S']);
        $p = Product::create(['sku' => 'SOF-'.$number, 'description' => 'x', 'supplier_id' => $sup->id, 'net_cost' => $reservationCost]);
        $job = BusinessJob::create(['job_number' => $number, 'job_name' => "Job {$number}", 'status' => 'active']);
        $res = JobReservation::create(['business_job_id' => $job->id, 'job_number' => $number, 'job_name' => $job->job_name, 'requested_by' => 'T', 'status' => 'active']);
        JobReservationItem::create(['reservation_id' => $res->id, 'product_id' => $p->id, 'requested_qty' => 1, 'committed_qty' => 1, 'consumed_qty' => 0]);

        $path = "job_documents/{$job->id}/est.xlsx";
        Storage::disk('local')->makeDirectory("job_documents/{$job->id}");
        $this->estimate($path, $net);

        return JobDocument::create([
            'business_job_id' => $job->id, 'job_reservation_id' => $res->id, 'doc_type' => 'ez_estimate',
            'original_name' => 'EZ.xlsx', 'file_path' => $path, 'archived' => $archived,
        ]);
    }

    public function test_reader_pulls_the_summary_figures_and_rejects_a_non_numeric_total(): void
    {
        Storage::fake('local');
        $this->estimate('a.xlsx', 1234.5, 100, 1334.5, 2000);
        $r = (new EzEstimateSummaryReader)->read(Storage::disk('local')->path('a.xlsx'));
        $this->assertSame(['total_net' => 1234.5, 'freight' => 100.0, 'grand_total' => 1334.5, 'list_price' => 2000.0], $r);

        $this->estimate('b.xlsx', "FINISH SET UP REQ'D");
        $this->expectException(\RuntimeException::class);
        (new EzEstimateSummaryReader)->read(Storage::disk('local')->path('b.xlsx'));
    }

    public function test_report_compares_ez_net_to_reservation_cost_per_job(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]), ['*']);
        $this->jobWithUpload('S-1', 1000, 700);
        $this->jobWithUpload('S-2', 500, 600);                  // over the estimate: negative savings
        $this->jobWithUpload('S-3', 9999, 1, archived: true);   // archived upload: ignored
        $this->jobWithUpload('S-4', "FINISH SET UP REQ'D", 50); // unreadable: reported, not counted

        $r = $this->getJson('/api/v1/reports/job-sof-savings')->assertOk()->json();

        $this->assertSame(1500.0, (float) $r['summary']['total_ez_net']);
        $this->assertSame(1300.0, (float) $r['summary']['total_reservation_cost']);
        $this->assertSame(200.0, (float) $r['summary']['total_savings']);
        $this->assertSame(2, $r['summary']['jobs']);
        $this->assertSame(1, $r['summary']['read_errors']);
        $s1 = collect($r['jobs'])->firstWhere('job_number', 'S-1');
        $this->assertSame([300.0, 30.0], [(float) $s1['savings'], (float) $s1['savings_pct']]);
        $this->assertNull(collect($r['jobs'])->firstWhere('job_number', 'S-3'));
        $this->assertNotNull(JobDocument::where('original_name', 'EZ.xlsx')->first()->ez_summary_read_at);
    }

    public function test_report_and_csv_are_limited_to_cost_viewers(): void
    {
        Storage::fake('local');
        $this->jobWithUpload('S-9', 100, 40);

        Sanctum::actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]), ['*']);
        $this->getJson('/api/v1/reports/job-sof-savings')->assertOk();
        $this->get('/api/v1/reports/export?type=sof_savings')->assertOk()->assertHeader('content-type', 'text/csv; charset=utf-8');

        foreach (['viewer', 'fabricator', 'retail'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'is_active' => true]), ['*']);
            $this->getJson('/api/v1/reports/job-sof-savings')->assertForbidden();
            $this->getJson('/api/v1/reports/export?type=sof_savings')->assertForbidden();
        }
    }
}
