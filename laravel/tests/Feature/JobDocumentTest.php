<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\JobDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Job-level document storage:
 *  - admin: full control (upload / delete / download / list)
 *  - manager, office_staff: read only (list / download)
 *  - everyone else: no access
 */
class JobDocumentTest extends TestCase
{
    use RefreshDatabase;

    private BusinessJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->job = BusinessJob::create(['job_number' => 'J1', 'job_name' => 'Job One', 'status' => 'active']);
    }

    private function actAs(string $role): User
    {
        $u = User::factory()->create(['role' => $role, 'is_active' => true]);
        Sanctum::actingAs($u, ['*']);

        return $u;
    }

    public function test_admin_can_upload_list_download_and_delete(): void
    {
        $this->actAs('admin');

        $res = $this->post("/api/v1/business-jobs/{$this->job->id}/documents", [
            'doc_type' => 'purchase_order',
            'label' => 'PO #4821',
            'file' => UploadedFile::fake()->create('po.pdf', 40, 'application/pdf'),
        ])->assertCreated()
            ->assertJsonPath('doc_type', 'purchase_order')
            ->assertJsonPath('label', 'PO #4821');

        $docId = $res->json('id');
        $doc = JobDocument::find($docId);
        Storage::disk('local')->assertExists($doc->file_path);

        $this->getJson("/api/v1/business-jobs/{$this->job->id}/documents")
            ->assertOk()
            ->assertJsonCount(1, 'documents')
            ->assertJsonPath('types.sof', 'SOF');

        $this->get("/api/v1/business-jobs/{$this->job->id}/documents/{$docId}/download")
            ->assertOk()
            ->assertDownload('po.pdf');

        $this->deleteJson("/api/v1/business-jobs/{$this->job->id}/documents/{$docId}")->assertOk();
        $this->assertDatabaseMissing('job_documents', ['id' => $docId]);
        Storage::disk('local')->assertMissing($doc->file_path);
    }

    public function test_other_type_requires_a_label(): void
    {
        $this->actAs('admin');

        $this->postJson("/api/v1/business-jobs/{$this->job->id}/documents", [
            'doc_type' => 'other',
            'file' => UploadedFile::fake()->create('misc.pdf', 10, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('label');
    }

    public function test_manager_and_office_can_read_but_not_manage(): void
    {
        $seed = JobDocument::create([
            'business_job_id' => $this->job->id, 'doc_type' => 'sof', 'original_name' => 's.pdf',
            'file_path' => 'job_documents/'.$this->job->id.'/x.pdf', 'file_size' => 1, 'file_mime' => 'application/pdf',
        ]);
        Storage::disk('local')->put($seed->file_path, 'x');

        foreach (['manager', 'office_staff'] as $role) {
            $this->actAs($role);

            $this->getJson("/api/v1/business-jobs/{$this->job->id}/documents")
                ->assertOk()->assertJsonCount(1, 'documents');
            $this->get("/api/v1/business-jobs/{$this->job->id}/documents/{$seed->id}/download")->assertOk();

            $this->postJson("/api/v1/business-jobs/{$this->job->id}/documents", [
                'doc_type' => 'sof',
                'file' => UploadedFile::fake()->create('n.pdf', 10, 'application/pdf'),
            ])->assertForbidden();

            $this->deleteJson("/api/v1/business-jobs/{$this->job->id}/documents/{$seed->id}")->assertForbidden();
        }
    }

    public function test_roles_without_view_permission_are_denied(): void
    {
        $this->actAs('fabricator');
        $this->getJson("/api/v1/business-jobs/{$this->job->id}/documents")->assertForbidden();
    }
}
