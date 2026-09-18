<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
    }

    public function test_upload_stores_the_logo_and_exposes_a_url(): void
    {
        $res = $this->postJson('/api/v1/company-settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 80),
        ])->assertOk();

        $path = $res->json('company_setting.logo_path');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertNotNull($res->json('company_setting.logo_url'));
        $this->assertSame($path, CompanySetting::current()->logo_path);
    }

    public function test_uploading_again_replaces_the_previous_file(): void
    {
        $first = $this->postJson('/api/v1/company-settings/logo', [
            'logo' => UploadedFile::fake()->image('a.png'),
        ])->json('company_setting.logo_path');

        $second = $this->postJson('/api/v1/company-settings/logo', [
            'logo' => UploadedFile::fake()->image('b.png'),
        ])->json('company_setting.logo_path');

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_delete_removes_the_logo(): void
    {
        $path = $this->postJson('/api/v1/company-settings/logo', [
            'logo' => UploadedFile::fake()->image('a.png'),
        ])->json('company_setting.logo_path');

        $this->deleteJson('/api/v1/company-settings/logo')->assertOk();

        Storage::disk('public')->assertMissing($path);
        $this->assertNull(CompanySetting::current()->logo_path);
    }

    public function test_non_image_is_rejected(): void
    {
        $this->postJson('/api/v1/company-settings/logo', [
            'logo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])->assertStatus(422);
    }

    public function test_requires_settings_edit_permission(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'worker', 'is_active' => true]), ['*']);

        $this->postJson('/api/v1/company-settings/logo', [
            'logo' => UploadedFile::fake()->image('a.png'),
        ])->assertStatus(403);
    }

    public function test_purchase_order_pdf_still_renders_with_a_logo_set(): void
    {
        $this->postJson('/api/v1/company-settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 80),
        ])->assertOk();

        $supplier = Supplier::create(['name' => 'Acme']);
        $product = Product::create(['sku' => 'X1', 'description' => 'X', 'net_cost' => 1, 'supplier_id' => $supplier->id]);
        $po = PurchaseOrder::create([
            'po_number' => 'PO-LOGO-1',
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'order_date' => now()->toDateString(),
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity_ordered' => 2,
            'unit_cost' => 1,
            'total_cost' => 2,
        ]);

        $res = $this->get("/api/v1/purchase-orders/{$po->id}/pdf");
        $res->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }
}
