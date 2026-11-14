<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Product library (docs/plans/pm-suite-integration.md, feature #10). */
class PmProductLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
    }

    private function product(array $over = []): array
    {
        $m = $this->postJson('/api/v1/pm/library/manufacturers', ['name' => 'Kawneer'.rand(1, 99999)])->assertCreated()->json();
        $c = $this->postJson('/api/v1/pm/library/categories', ['name' => 'Storefront'.rand(1, 99999)])->assertCreated()->json();

        return $this->postJson('/api/v1/pm/library/products', array_merge([
            'pm_manufacturer_id' => $m['id'], 'pm_product_category_id' => $c['id'], 'name' => 'Trifab 451T', 'model' => '451T',
        ], $over))->assertCreated()->json();
    }

    public function test_manufacturer_category_product_crud_and_filters(): void
    {
        $p = $this->product();
        $this->assertSame('Trifab 451T', $p['name']);

        $this->putJson("/api/v1/pm/library/products/{$p['id']}", ['pm_manufacturer_id' => $p['pm_manufacturer_id'], 'name' => 'Trifab 451UT', 'is_active' => true])->assertOk();
        $this->assertCount(1, $this->getJson('/api/v1/pm/library/products?search=451UT')->assertOk()->json());
        $this->assertCount(1, $this->getJson("/api/v1/pm/library/products?manufacturer_id={$p['pm_manufacturer_id']}")->json());

        $this->putJson("/api/v1/pm/library/products/{$p['id']}", ['pm_manufacturer_id' => $p['pm_manufacturer_id'], 'name' => 'Old', 'is_active' => false])->assertOk();
        $this->assertCount(0, $this->getJson('/api/v1/pm/library/products')->json());
        $this->assertCount(1, $this->getJson('/api/v1/pm/library/products?include_inactive=1')->json());

        // a manufacturer with products cannot be deleted; duplicates are refused
        $this->deleteJson("/api/v1/pm/library/manufacturers/{$p['pm_manufacturer_id']}")->assertStatus(422);
        $name = $this->getJson('/api/v1/pm/library/lookups')->json('manufacturers.0.name');
        $this->postJson('/api/v1/pm/library/manufacturers', ['name' => $name])->assertStatus(422);
    }

    public function test_documents_upload_and_download(): void
    {
        $p = $this->product();
        $res = $this->post("/api/v1/pm/library/products/{$p['id']}/documents", [
            'file' => UploadedFile::fake()->createWithContent('451T-spec.pdf', '%PDF-1 spec sheet'), 'kind' => 'spec', 'label' => 'Spec sheet',
        ], ['Accept' => 'application/json'])->assertCreated();
        $doc = $res->json('documents.0');
        $this->assertSame('spec', $doc['kind']);
        $this->assertSame('451T-spec.pdf', $doc['file']['original_name']);

        $this->get("/api/v1/pm/files/{$doc['pm_file_id']}/download")->assertOk()->assertDownload('451T-spec.pdf');

        $this->post("/api/v1/pm/library/products/{$p['id']}/documents", ['file' => UploadedFile::fake()->createWithContent('x.pdf', 'x'), 'kind' => 'bogus'], ['Accept' => 'application/json'])->assertStatus(422);
        $this->deleteJson("/api/v1/pm/library/products/{$p['id']}/documents/{$doc['id']}")->assertOk()->assertJsonCount(0, 'documents');
    }

    public function test_only_library_managers_can_edit_but_pms_can_read(): void
    {
        $p = $this->product();

        Sanctum::actingAs(User::factory()->create(['role' => 'project_manager', 'is_active' => true]), ['*']);
        $this->getJson('/api/v1/pm/library/products')->assertOk()->assertJsonCount(1);
        $this->postJson('/api/v1/pm/library/manufacturers', ['name' => 'Nope'])->assertForbidden();
        $this->putJson("/api/v1/pm/library/products/{$p['id']}", ['pm_manufacturer_id' => $p['pm_manufacturer_id'], 'name' => 'x'])->assertForbidden();
    }

    public function test_job_file_downloads_follow_job_scope(): void
    {
        $owner = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $job = BusinessJob::withoutGlobalScopes()->create(['job_number' => '6001', 'job_name' => 'J', 'status' => 'won', 'project_manager_id' => $owner->id]);

        Sanctum::actingAs($owner, ['*']);
        $fileId = $this->post("/api/v1/pm/jobs/{$job->id}/estimates/upload", ['file' => UploadedFile::fake()->createWithContent('b.xlsx', 'PK-x')], ['Accept' => 'application/json']);
        // the worker is not running in tests: the file is still registered before the sheet lookup fails
        $id = \App\Models\PmFile::firstOrFail()->id;
        $this->get("/api/v1/pm/files/{$id}/download")->assertOk();

        Sanctum::actingAs(User::factory()->create(['role' => 'project_manager', 'is_active' => true]), ['*']);
        $this->get("/api/v1/pm/files/{$id}/download")->assertNotFound();
    }

    public function test_library_page_renders(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->get('/pm/library')->assertOk()->assertSee('Product Library');
    }

    public function test_unplaced_files_are_only_downloadable_by_people_who_manage_the_inbox(): void
    {
        $file = \App\Models\PmFile::create(['source' => 'fdrive', 'doc_type' => 'unclassified', 'original_name' => 'loose.pdf', 'storage_path' => 'pm_files/loose.pdf',
            'sha256' => str_repeat('a', 64), 'size' => 3]);
        Storage::disk('local')->put('pm_files/loose.pdf', 'abc');

        $this->get("/api/v1/pm/files/{$file->id}/download")->assertOk();           // admin
        Sanctum::actingAs(User::factory()->create(['role' => 'superintendent', 'is_active' => true]), ['*']);
        $this->get("/api/v1/pm/files/{$file->id}/download")->assertNotFound();      // can view PM data but does not manage the inbox
    }
}
