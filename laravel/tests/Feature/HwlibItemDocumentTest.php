<?php

namespace Tests\Feature;

use App\Models\ConfiguratorHwlibCategory;
use App\Models\ConfiguratorHwlibItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HwlibItemDocumentTest extends TestCase
{
    use RefreshDatabase;

    private ConfiguratorHwlibItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $cat = ConfiguratorHwlibCategory::create(['name' => 'Locks']);
        $this->item = ConfiguratorHwlibItem::create(['category_id' => $cat->id, 'name' => 'Mortise Lock']);
    }

    private function upload(string $name = 'prep-template.pdf', array $extra = [])
    {
        return $this->postJson("/api/v1/config/hwlib-items/{$this->item->id}/documents", $extra + [
            'doc_type' => 'template',
            'file' => UploadedFile::fake()->create($name, 120, 'application/pdf'),
        ]);
    }

    public function test_upload_list_and_delete(): void
    {
        $res = $this->upload()->assertCreated();
        $doc = $res->json('documents.0');
        $this->assertSame('prep-template', $doc['title']); // defaults to the file name
        $this->assertSame('template', $doc['doc_type']);
        Storage::disk('public')->assertExists($doc['file_path']);

        $this->upload('manual.pdf', ['doc_type' => 'parts_manual', 'title' => 'Parts'])->assertCreated();
        $admin = $this->getJson('/api/v1/config/hwlib-admin')->assertOk()->json('items.0.documents');
        $this->assertCount(2, $admin);

        $this->deleteJson("/api/v1/config/hwlib-item-documents/{$doc['id']}")->assertOk()->assertJsonCount(1, 'documents');
        Storage::disk('public')->assertMissing($doc['file_path']);
    }

    public function test_rename_and_retype(): void
    {
        $id = $this->upload()->json('documents.0.id');
        $this->putJson("/api/v1/config/hwlib-item-documents/{$id}", ['doc_type' => 'instructions', 'title' => 'Install guide'])
            ->assertOk()->assertJsonPath('documents.0.title', 'Install guide')->assertJsonPath('documents.0.doc_type', 'instructions');
    }

    public function test_rejects_dangerous_files_and_bad_types(): void
    {
        $this->upload('evil.html')->assertStatus(422);
        $this->upload('evil.php.pdf')->assertStatus(422);
        $this->upload('ok.pdf', ['doc_type' => 'bogus'])->assertStatus(422);
        $this->assertSame(0, $this->item->documents()->count());
    }

    public function test_deleting_item_removes_files(): void
    {
        $path = $this->upload()->json('documents.0.file_path');
        $this->deleteJson("/api/v1/config/hwlib-items/{$this->item->id}")->assertOk();
        Storage::disk('public')->assertMissing($path);
    }
}
