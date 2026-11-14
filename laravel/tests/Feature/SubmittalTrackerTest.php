<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Submittal tracker (docs/plans/pm-suite-integration.md, feature #7). */
class SubmittalTrackerTest extends TestCase
{
    use RefreshDatabase;

    private BusinessJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->job = BusinessJob::withoutGlobalScopes()->create(['job_number' => '8001', 'job_name' => 'S', 'status' => 'won', 'origin' => 'pm']);
    }

    private function url(string $suffix = ''): string
    {
        return "/api/v1/pm/jobs/{$this->job->id}/submittals{$suffix}";
    }

    private function create(string $name = 'Storefront shop drawings'): array
    {
        return $this->postJson($this->url(), ['name' => $name, 'spec_section' => '08 41 13'])->assertCreated()->json();
    }

    public function test_numbers_are_sequential_per_job(): void
    {
        $a = $this->create();
        $b = $this->create('Glass');
        $this->assertSame(['001', '002'], [$a['number'], $b['number']]);
        $this->assertSame('pending', $a['status']);
        $this->assertSame('docs', $a['kind']);

        $other = BusinessJob::withoutGlobalScopes()->create(['job_number' => '8002', 'job_name' => 'O', 'status' => 'won']);
        $this->assertSame('001', $this->postJson("/api/v1/pm/jobs/{$other->id}/submittals", ['name' => 'x'])->json('number'));
    }

    public function test_status_revision_history_and_response_upload(): void
    {
        $s = $this->create();

        $this->putJson($this->url("/{$s['id']}"), ['status' => 'bogus'])->assertStatus(422);
        $this->putJson($this->url("/{$s['id']}"), ['sent_at' => '2026-11-01'])->assertOk();

        $res = $this->post($this->url("/{$s['id']}/responses"), [
            'file' => UploadedFile::fake()->createWithContent('markup.pdf', '%PDF-1 response'),
            'status' => 'revise_resubmit', 'returned_at' => '2026-11-10', 'responded_by' => 'Architect',
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame('revise_resubmit', $res->json('status'));
        $this->assertSame('submittal_response', $res->json('responses.0.file.doc_type'));

        $rev = $this->postJson($this->url("/{$s['id']}/revise"))->assertOk();
        $this->assertSame(1, $rev->json('revision_number'));
        $this->assertSame('pending', $rev->json('status'));
        $this->assertNull($rev->json('sent_at'));
        $this->assertSame('revise_resubmit', $rev->json('revisions.0.status'));   // the closed-out round
        $this->assertSame(1, count($rev->json('responses')));                      // responses are kept

        // pending is not a valid response status
        $this->post($this->url("/{$s['id']}/responses"), ['status' => 'pending', 'returned_at' => '2026-11-11'], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_samples_change_kind_and_drive_needs_response_filter(): void
    {
        $docs = $this->create('Docs only');
        $withSample = $this->create('With sample');
        $this->putJson($this->url("/{$docs['id']}"), ['status' => 'approved'])->assertOk();
        $this->putJson($this->url("/{$withSample['id']}"), ['status' => 'approved'])->assertOk();

        $res = $this->postJson($this->url("/{$withSample['id']}/samples"), ['description' => 'Clear anodized 6in', 'manufacturer' => 'Kawneer'])->assertCreated();
        $this->assertSame('docs_samples', $res->json('kind'));

        $needs = collect($this->getJson($this->url('?needs_response=1'))->assertOk()->json())->pluck('number');
        $this->assertSame(['002'], $needs->all());   // approved docs but a sample is still pending

        $sampleId = $res->json('samples.0.id');
        $this->putJson($this->url("/{$withSample['id']}/samples/{$sampleId}"), ['status' => 'approved', 'returned_at' => '2026-11-12'])->assertOk();
        $this->assertCount(0, $this->getJson($this->url('?needs_response=1'))->json());
        $this->deleteJson($this->url("/{$withSample['id']}/samples/{$sampleId}"))->assertOk()->assertJsonCount(0, 'samples');
    }

    public function test_links_only_job_pos_of_the_same_job_and_respects_scope(): void
    {
        $s = $this->create();
        $vendor = Supplier::create(['name' => 'V', 'is_active' => true]);
        $line = [['description' => 'x', 'quantity' => 1, 'unit_cost' => 5]];
        $po = $this->postJson('/api/v1/job-purchase-orders', ['supplier_id' => $vendor->id, 'business_job_id' => $this->job->id,
            'order_date' => now()->toDateString(), 'lines' => $line])->json('purchase_order');
        $otherJob = BusinessJob::withoutGlobalScopes()->create(['job_number' => '8003', 'job_name' => 'O', 'status' => 'won']);
        $otherPo = $this->postJson('/api/v1/job-purchase-orders', ['supplier_id' => $vendor->id, 'business_job_id' => $otherJob->id,
            'order_date' => now()->toDateString(), 'lines' => $line])->json('purchase_order');

        $this->postJson($this->url("/{$s['id']}/purchase-orders"), ['purchase_order_id' => $otherPo['id']])->assertNotFound();
        $res = $this->postJson($this->url("/{$s['id']}/purchase-orders"), ['purchase_order_id' => $po['id']])->assertOk();
        $this->assertSame($po['po_number'], $res->json('purchase_orders.0.po_number'));
        $this->deleteJson($this->url("/{$s['id']}/purchase-orders/{$po['id']}"))->assertOk()->assertJsonCount(0, 'purchase_orders');

        // a PM who cannot see the job cannot see its submittals
        $stranger = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        Sanctum::actingAs($stranger, ['*']);
        $this->getJson($this->url())->assertNotFound();
        $this->postJson($this->url(), ['name' => 'x'])->assertNotFound();
    }

    public function test_job_page_has_the_submittals_card(): void
    {
        $this->get("/pm/jobs/{$this->job->id}")->assertOk()->assertSee('Submittals')->assertSee('Record GC/AE response');
    }

    public function test_builder_assembles_items_and_builds_a_numbered_package(): void
    {
        config(['services.pm_worker.token' => 'tok', 'services.pm_worker.url' => 'http://worker.test']);
        Http::fake(['worker.test/pdf/merge' => Http::response('%PDF-merged', 200, ['Content-Type' => 'application/pdf'])]);
        $s = $this->create();

        // a library document and an uploaded PDF
        $m = $this->postJson('/api/v1/pm/library/manufacturers', ['name' => 'Kawneer'])->json();
        $p = $this->postJson('/api/v1/pm/library/products', ['pm_manufacturer_id' => $m['id'], 'name' => 'Trifab'])->json();
        $doc = $this->post("/api/v1/pm/library/products/{$p['id']}/documents", ['file' => UploadedFile::fake()->createWithContent('spec.pdf', '%PDF-1 a'), 'kind' => 'spec'], ['Accept' => 'application/json'])->json('documents.0');

        $this->postJson($this->url("/{$s['id']}/items"), ['product_document_id' => $doc['id']])->assertCreated()->assertJsonPath('items.0.label', 'Trifab — spec.pdf');
        $this->post($this->url("/{$s['id']}/items"), ['file' => UploadedFile::fake()->createWithContent('shop.pdf', '%PDF-1 b'), 'label' => 'Shop drawings'], ['Accept' => 'application/json'])->assertCreated();
        $this->post($this->url("/{$s['id']}/items"), ['file' => UploadedFile::fake()->createWithContent('x.docx', 'x')], ['Accept' => 'application/json'])->assertStatus(422);

        $items = $this->getJson($this->url("/{$s['id']}"))->json('items');
        $this->assertSame(['Trifab — spec.pdf', 'Shop drawings'], array_column($items, 'label'));
        $reordered = $this->postJson($this->url("/{$s['id']}/items/reorder"), ['order' => [$items[1]['id'], $items[0]['id']]])->assertOk();
        $this->assertSame(['Shop drawings', 'Trifab — spec.pdf'], array_column($reordered->json('items'), 'label'));
        $this->postJson($this->url("/{$s['id']}/items/reorder"), ['order' => [$items[0]['id']]])->assertStatus(422);

        $built = $this->postJson($this->url("/{$s['id']}/package"))->assertCreated();
        $this->assertSame('Submittal_8001_001.pdf', $built->json('packages.0.file.original_name'));
        $this->assertSame(2, $built->json('packages.0.item_count'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/pdf/merge') && $r->header('X-Worker-Token')[0] === 'tok');

        // revision 1 builds a separately named package; history is kept
        $this->postJson($this->url("/{$s['id']}/revise"))->assertOk();
        $built = $this->postJson($this->url("/{$s['id']}/package"))->assertCreated();
        $this->assertSame(['Submittal_8001_001_Rev1.pdf', 'Submittal_8001_001.pdf'], array_column(array_column($built->json('packages'), 'file'), 'original_name'));

        $this->deleteJson($this->url("/{$s['id']}/items/{$items[0]['id']}"))->assertOk()->assertJsonCount(1, 'items');
    }

    public function test_package_needs_items_and_reports_worker_problems(): void
    {
        config(['services.pm_worker.token' => 'tok', 'services.pm_worker.url' => 'http://worker.test']);
        $s = $this->create();
        $this->postJson($this->url("/{$s['id']}/package"))->assertStatus(422);

        $this->post($this->url("/{$s['id']}/items"), ['file' => UploadedFile::fake()->createWithContent('a.pdf', '%PDF-1'), 'label' => 'A'], ['Accept' => 'application/json'])->assertCreated();
        Http::fake(['worker.test/pdf/merge' => Http::response(['error' => '0001.pdf: broken'], 422)]);
        $this->postJson($this->url("/{$s['id']}/package"))->assertStatus(422)->assertJsonPath('message', '0001.pdf: broken');
    }
}
