<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Material forecast: estimate vs job POs, never stock POs (docs/plans/pm-suite-integration.md). */
class MaterialForecastTest extends TestCase
{
    use RefreshDatabase;

    private BusinessJob $job;

    private Supplier $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pm_worker.token' => 'tok', 'services.pm_worker.url' => 'http://worker.test']);
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->job = BusinessJob::withoutGlobalScopes()->create(['job_number' => '9400', 'job_name' => 'Fc', 'status' => 'won', 'origin' => 'pm']);
        $this->vendor = Supplier::create(['name' => 'Glass Co', 'is_active' => true]);

        $line = fn ($row, $code, $qty, $total) => ['section' => 'material', 'row' => $row, 'who' => 'x', 'costCode' => $code,
            'description' => "L$row", 'qty' => $qty, 'unitPrice' => $total / $qty, 'total' => $total, 'confidence' => 'parsed'];
        Http::fake([
            'worker.test/estimate/sheets' => Http::response(['sheets' => ['CONTRACT', 'CO #1']]),
            'worker.test/estimate/parse?sheet=CONTRACT' => Http::response(['parserRevision' => 550, 'summary' => [],
                'lines' => [$line(1, 200, 10, 1000), $line(2, 230, 4, 800), $line(3, 100, 8, 400)], 'reviewNeeded' => []]),
            'worker.test/estimate/parse?sheet=CO+%231' => Http::response(['parserRevision' => 550, 'summary' => [],
                'lines' => [$line(1, 200, 2, 300)], 'reviewNeeded' => []]),
        ]);
        foreach (['CONTRACT', 'CO #1'] as $i => $sheet) {
            $id = $this->postJson("/api/v1/pm/jobs/{$this->job->id}/estimates/upload", ['file' => UploadedFile::fake()->createWithContent('b.xlsx', "wb$i")])->json('file.id');
            $this->postJson("/api/v1/pm/jobs/{$this->job->id}/estimates/import", ['pm_file_id' => $id, 'sheet' => $sheet])->assertOk();
        }
    }

    private function jobPo(string $stage, array $lines): array
    {
        return $this->postJson('/api/v1/job-purchase-orders', [
            'supplier_id' => $this->vendor->id, 'business_job_id' => $this->job->id, 'order_date' => now()->toDateString(),
            'issue_stage' => $stage, 'lines' => $lines,
        ])->assertCreated()->json('purchase_order');
    }

    private function forecast(string $qs = '')
    {
        return $this->getJson("/api/v1/pm/jobs/{$this->job->id}/forecast{$qs}")->assertOk();
    }

    public function test_estimate_only_shows_not_ordered_for_material_codes(): void
    {
        $res = $this->forecast();
        $rows = collect($res->json('rows'))->keyBy('costCode');

        $this->assertEquals(1300, $rows[200]['estimatedTotal']);   // contract + change order
        $this->assertEquals(12, $rows[200]['estimatedQty']);
        $this->assertSame('not_ordered', $rows[200]['status']);
        $this->assertFalse($rows->has(100));                       // labor is not material
        $this->assertEquals(2100, $res->json('totals.estimatedTotal'));

        $this->assertCount(2, $this->forecast('?sheets=CONTRACT&category=material')->json('rows'));
        $this->assertEquals(1000, collect($this->forecast('?sheets=CONTRACT')->json('rows'))->firstWhere('costCode', 200)['estimatedTotal']);
        $this->assertTrue(collect($this->forecast('?category=all')->json('rows'))->contains('costCode', 100));
    }

    public function test_ordered_received_draft_and_voided_pos(): void
    {
        $issued = $this->jobPo('material-release', [['description' => 'Lites', 'quantity' => 10, 'unit_cost' => 90, 'cost_code' => '200']]);
        $this->jobPo('draft', [['description' => 'More lites', 'quantity' => 2, 'unit_cost' => 100, 'cost_code' => '200']]);
        $voided = $this->jobPo('material-release', [['description' => 'Oops', 'quantity' => 99, 'unit_cost' => 1, 'cost_code' => '200']]);
        $this->postJson("/api/v1/job-purchase-orders/{$voided['id']}/void", ['reason' => 'Wrong job'])->assertOk();

        $row = fn () => collect($this->forecast()->json('rows'))->firstWhere('costCode', 200);

        $this->assertEquals(900, $row()['orderedTotal']);
        $this->assertEquals(200, $row()['draftTotal']);            // planned, not placed
        $this->assertEquals(69.2, $row()['orderedPct']);
        $this->assertSame('ordered', $row()['status']);

        $this->postJson("/api/v1/job-purchase-orders/{$issued['id']}/receive", ['receipts' => [['item_id' => $issued['lines'][0]['id'], 'quantity' => 4]]])->assertOk();
        $this->assertSame('partially_received', $row()['status']);
        $this->assertEquals(360, $row()['receivedTotal']);
        $this->assertEquals(40.0, $row()['receivedPct']);

        $this->postJson("/api/v1/job-purchase-orders/{$issued['id']}/receive", ['receipts' => [['item_id' => $issued['lines'][0]['id'], 'quantity' => 6]]])->assertOk();
        $this->assertSame('received', $row()['status']);
    }

    public function test_over_budget_group_lines_and_stock_pos_are_handled(): void
    {
        $this->jobPo('material-release', [['description' => 'Frames group', 'kind' => 'alum', 'cost_code' => '230', 'children' => [
            ['description' => 'A', 'quantity' => 4, 'unit_cost' => 150],
            ['description' => 'B', 'quantity' => 1, 'unit_cost' => 400],
        ]]]);
        $row = collect($this->forecast()->json('rows'))->firstWhere('costCode', 230);
        $this->assertEquals(1000, $row['orderedTotal']);           // children counted once, group heading ignored
        $this->assertSame('over_budget', $row['status']);          // estimate was 800

        // A stock PO to the same vendor for the same code must not appear as job material
        $p = Product::create(['sku' => 'S', 'description' => 'S', 'supplier_id' => $this->vendor->id, 'quantity_on_hand' => 0, 'on_order_qty' => 0]);
        $this->postJson('/api/v1/purchase-orders', ['supplier_id' => $this->vendor->id, 'order_date' => now()->toDateString(),
            'items' => [['product_id' => $p->id, 'quantity' => 5, 'unit_cost' => 10, 'cost_code' => '200']]])->assertCreated();
        $this->assertEquals(0, collect($this->forecast()->json('rows'))->firstWhere('costCode', 200)['orderedTotal']);
    }

    public function test_job_page_has_the_forecast_card(): void
    {
        $this->get("/pm/jobs/{$this->job->id}")->assertOk()->assertSee('Material forecast');
    }
}
