<?php

namespace Tests\Feature;

use App\Models\FabricationDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FabricationDocumentRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
    }

    private function create(string $name = 'a.pdf'): int
    {
        return $this->post('/api/v1/fabrication-documents', [
            'title' => 'Closer template',
            'type' => 'fabrication',
            'tags' => json_encode(['closer', 'lcn']),
            'file' => UploadedFile::fake()->create($name, 10, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('revision', 'v1')->json('id');
    }

    public function test_replacing_the_file_archives_the_old_one_as_a_revision(): void
    {
        $id = $this->create('a.pdf');
        $oldPath = FabricationDocument::find($id)->file_path;

        $this->post("/api/v1/fabrication-documents/{$id}", [
            '_method' => 'PUT',
            'title' => 'Closer template',
            'type' => 'fabrication',
            'file' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
        ])->assertOk()->assertJsonPath('revision', 'v2')->assertJsonPath('revisions_count', 1)
            ->assertJsonPath('revisions.0.revision', 'v1')
            ->assertJsonPath('revisions.0.file.name', 'a.pdf');

        Storage::disk('public')->assertExists($oldPath);
    }

    public function test_restore_swaps_the_revision_in_and_keeps_the_replaced_file(): void
    {
        $id = $this->create('a.pdf');
        $this->post("/api/v1/fabrication-documents/{$id}", [
            '_method' => 'PUT', 'title' => 'T', 'type' => 'fabrication', 'revision' => 'Rev B',
            'file' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
        ])->assertOk()->assertJsonPath('revision', 'Rev B');

        $revId = $this->getJson("/api/v1/fabrication-documents/{$id}")->json('revisions.0.id');

        $this->postJson("/api/v1/fabrication-documents/{$id}/revisions/{$revId}/restore")
            ->assertOk()
            ->assertJsonPath('file.name', 'a.pdf')
            ->assertJsonPath('revision', 'v1')
            ->assertJsonPath('revisions_count', 1)
            ->assertJsonPath('revisions.0.revision', 'Rev B')
            ->assertJsonPath('revisions.0.file.name', 'b.pdf');
    }

    public function test_deleting_a_revision_removes_its_file_and_deleting_the_document_removes_all(): void
    {
        $id = $this->create('a.pdf');
        $this->post("/api/v1/fabrication-documents/{$id}", [
            '_method' => 'PUT', 'title' => 'T', 'type' => 'fabrication',
            'file' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
        ])->assertOk();
        $rev = FabricationDocument::find($id)->revisions()->first();

        $this->deleteJson("/api/v1/fabrication-documents/{$id}/revisions/{$rev->id}")
            ->assertOk()->assertJsonPath('revisions_count', 0);
        Storage::disk('public')->assertMissing($rev->file_path);

        $this->deleteJson("/api/v1/fabrication-documents/{$id}")->assertOk();
        $this->assertSoftDeleted('fabrication_documents', ['id' => $id]);
    }

    public function test_a_revision_cannot_be_restored_through_another_document(): void
    {
        $a = $this->create('a.pdf');
        $b = $this->create('c.pdf');
        $this->post("/api/v1/fabrication-documents/{$a}", [
            '_method' => 'PUT', 'title' => 'T', 'type' => 'fabrication',
            'file' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
        ])->assertOk();
        $rev = FabricationDocument::find($a)->revisions()->first();

        $this->postJson("/api/v1/fabrication-documents/{$b}/revisions/{$rev->id}/restore")->assertNotFound();
    }

    public function test_tag_filter(): void
    {
        $this->create('a.pdf');
        $this->getJson('/api/v1/fabrication-documents?tag=lcn')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/fabrication-documents?tag=nope')->assertOk()->assertJsonCount(0, 'data');
    }
}
