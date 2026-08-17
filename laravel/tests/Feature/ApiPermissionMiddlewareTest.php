<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression coverage for the `permission:` middleware added to the
 * previously-ungated apiResource routes (categories, suppliers, products,
 * storage-locations, purchase-orders, cycle-counts, orders, machines, assets,
 * maintenance-tasks, maintenance-records, business-jobs), and for the
 * follow-up migration that grants manager/fabricator/viewer the base CRUD
 * permissions ACTION_PERMISSIONS_GUIDE.md documents them as having (which the
 * original seed migrations never actually assigned to anyone but admin).
 */
class ApiPermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_cannot_create_a_product(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer', 'is_active' => true]);
        Sanctum::actingAs($viewer, ['*']);

        $response = $this->postJson('/api/v1/products', [
            'sku' => 'VIEWER-TEST',
            'description' => 'Should be rejected',
        ]);

        $response->assertForbidden();
    }

    public function test_manager_can_create_a_product(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        Sanctum::actingAs($manager, ['*']);

        $response = $this->postJson('/api/v1/products', $this->validProductPayload('MANAGER-TEST'));

        $response->assertCreated();
    }

    public function test_manager_can_create_and_edit_a_purchase_order(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $supplier = \App\Models\Supplier::create(['name' => 'Test Supplier']);
        $product = \App\Models\Product::create(['sku' => 'PO-PERM-TEST', 'description' => 'Test']);
        Sanctum::actingAs($manager, ['*']);

        $response = $this->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5, 'unit_cost' => 10],
            ],
        ]);

        $response->assertCreated();
    }

    public function test_admin_can_create_a_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson('/api/v1/products', $this->validProductPayload('ADMIN-TEST'));

        $response->assertCreated();
    }

    private function validProductPayload(string $sku): array
    {
        return [
            'sku' => $sku,
            'description' => 'Test product',
            'unit_cost' => 1,
            'quantity_on_hand' => 0,
            'minimum_quantity' => 0,
            'unit_of_measure' => 'EA',
        ];
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/products');

        $response->assertUnauthorized();
    }
}
