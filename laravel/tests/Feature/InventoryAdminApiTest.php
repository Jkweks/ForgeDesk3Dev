<?php

namespace Tests\Feature;

use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\Role;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * API-level coverage for the inventory reference data (storage locations, suppliers), the cycle-count
 * workflow end to end, and role administration.
 */
class InventoryAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($u, ['*']);

        return $u;
    }

    private function product(string $sku, ?Supplier $supplier = null): Product
    {
        return Product::create(['sku' => $sku, 'description' => $sku, 'supplier_id' => ($supplier ?? Supplier::firstOrCreate(['name' => 'Acme']))->id]);
    }

    // ── storage locations ──────────────────────────────────────────────────────

    public function test_storage_location_crud_and_uniqueness(): void
    {
        $this->admin();

        $id = $this->postJson('/api/v1/storage-locations', ['name' => 'Aisle 1', 'type' => 'aisle'])->assertSuccessful()->json('id') ?? StorageLocation::where('name', 'Aisle 1')->value('id');
        $this->postJson('/api/v1/storage-locations', ['name' => 'Aisle 1', 'type' => 'aisle'])->assertStatus(422);       // names are unique
        $this->postJson('/api/v1/storage-locations', ['name' => 'X', 'type' => 'not-a-type'])->assertStatus(422);

        $child = $this->postJson('/api/v1/storage-locations', ['name' => 'Aisle 1.Rack 1', 'type' => 'rack', 'parent_id' => $id])->assertSuccessful();
        $this->assertSame($id, StorageLocation::where('name', 'Aisle 1.Rack 1')->value('parent_id'));

        $this->putJson("/api/v1/storage-locations/{$id}", ['name' => 'Aisle One', 'type' => 'aisle'])->assertSuccessful();
        $this->assertSame('Aisle One', StorageLocation::find($id)->name);
    }

    public function test_a_storage_location_holding_stock_cannot_be_deleted(): void
    {
        $this->admin();
        $loc = StorageLocation::create(['name' => 'Shelf', 'code' => 'SH', 'type' => 'shelf']);
        $empty = StorageLocation::create(['name' => 'Empty', 'code' => 'EM', 'type' => 'shelf']);
        InventoryLocation::create(['product_id' => $this->product('SL-1')->id, 'storage_location_id' => $loc->id, 'quantity' => 3, 'is_primary' => true]);

        $this->deleteJson("/api/v1/storage-locations/{$loc->id}")->assertStatus(422);
        $this->assertNotNull(StorageLocation::find($loc->id));

        $this->deleteJson("/api/v1/storage-locations/{$empty->id}")->assertSuccessful();
        $this->assertNull(StorageLocation::find($empty->id));
    }

    // ── suppliers ──────────────────────────────────────────────────────────────

    public function test_supplier_crud_and_delete_protection(): void
    {
        $this->admin();

        $this->postJson('/api/v1/suppliers', ['name' => 'Widgets Inc', 'code' => 'WID'])->assertSuccessful();
        $this->postJson('/api/v1/suppliers', ['name' => 'Other', 'code' => 'WID'])->assertStatus(422);  // codes are unique
        $this->postJson('/api/v1/suppliers', ['code' => 'NONAME'])->assertStatus(422);

        $used = Supplier::create(['name' => 'Used']);
        $this->product('SUP-1', $used);
        $this->deleteJson("/api/v1/suppliers/{$used->id}")->assertStatus(422);

        $free = Supplier::where('code', 'WID')->first();
        $this->deleteJson("/api/v1/suppliers/{$free->id}")->assertSuccessful();
    }

    public function test_viewer_cannot_create_a_supplier_or_location(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]), ['*']);

        $this->postJson('/api/v1/suppliers', ['name' => 'Nope'])->assertForbidden();
        $this->postJson('/api/v1/storage-locations', ['name' => 'Nope', 'type' => 'rack'])->assertForbidden();
    }

    // ── cycle counts ───────────────────────────────────────────────────────────

    public function test_cycle_count_lifecycle_adjusts_the_bin_when_the_variance_is_approved(): void
    {
        $this->admin();
        $product = $this->product('CC-1');
        $loc = StorageLocation::create(['name' => 'CC Rack', 'code' => 'CCR', 'type' => 'rack']);
        $bin = InventoryLocation::create(['product_id' => $product->id, 'storage_location_id' => $loc->id, 'quantity' => 10, 'is_primary' => true]);

        $session = $this->postJson('/api/v1/cycle-counts', ['scheduled_date' => now()->toDateString(), 'product_ids' => [$product->id]])->assertSuccessful();
        $id = $session->json('id') ?? $session->json('session.id');
        $this->assertNotNull($id, $session->getContent());

        $this->postJson("/api/v1/cycle-counts/{$id}/start")->assertSuccessful();
        $item = \App\Models\CycleCountItem::where('session_id', $id)->first();
        $this->assertNotNull($item);

        // cannot finish while an item is uncounted
        $this->postJson("/api/v1/cycle-counts/{$id}/complete")->assertStatus(422);

        $this->postJson("/api/v1/cycle-counts/{$id}/record-count", ['item_id' => $item->id, 'counted_quantity' => 7])->assertSuccessful();
        $this->assertEquals(10, (float) $bin->fresh()->quantity); // counting alone changes nothing

        // a non-zero variance must be approved before the session can complete
        $this->postJson("/api/v1/cycle-counts/{$id}/complete")->assertStatus(422);

        $this->postJson("/api/v1/cycle-counts/{$id}/approve-variances", ['item_ids' => [$item->id]])->assertSuccessful();
        $this->assertEquals(7, (float) $bin->fresh()->quantity);
        $this->assertEquals(7, (float) \DB::table('products')->where('id', $product->id)->value('quantity_on_hand'));

        $this->postJson("/api/v1/cycle-counts/{$id}/complete")->assertSuccessful();
        $this->assertSame('completed', \App\Models\CycleCountSession::find($id)->status);
    }

    public function test_cycle_count_actions_respect_session_state_and_item_ownership(): void
    {
        $this->admin();
        $a = $this->product('CC-A');
        $b = $this->product('CC-B');
        $make = function (Product $p) {
            $r = $this->postJson('/api/v1/cycle-counts', ['scheduled_date' => now()->toDateString(), 'product_ids' => [$p->id]]);

            return $r->json('id') ?? $r->json('session.id');
        };
        $sessionA = $make($a);
        $sessionB = $make($b);
        $this->postJson("/api/v1/cycle-counts/{$sessionA}/start")->assertSuccessful();
        $this->postJson("/api/v1/cycle-counts/{$sessionA}/start")->assertStatus(422); // only planned sessions start

        $foreignItem = \App\Models\CycleCountItem::where('session_id', $sessionB)->first();
        $this->postJson("/api/v1/cycle-counts/{$sessionA}/record-count", ['item_id' => $foreignItem->id, 'counted_quantity' => 1])->assertStatus(422);
    }

    // ── roles ──────────────────────────────────────────────────────────────────

    public function test_role_management_rules(): void
    {
        $this->admin();

        $role = $this->postJson('/api/v1/roles', ['name' => 'kit_packer', 'display_name' => 'Kit Packer'])->assertSuccessful();
        $roleId = $role->json('id') ?? Role::where('name', 'kit_packer')->value('id');
        $this->postJson('/api/v1/roles', ['name' => 'kit_packer', 'display_name' => 'Dup'])->assertStatus(422);
        $this->postJson('/api/v1/roles', ['name' => 'bad name!', 'display_name' => 'Bad'])->assertStatus(422);

        // the admin role is fixed
        $adminRole = Role::where('name', 'admin')->first();
        $this->postJson("/api/v1/roles/{$adminRole->id}/permissions", ['permissions' => []])->assertForbidden();

        $this->deleteJson("/api/v1/roles/{$roleId}")->assertSuccessful();
    }

    public function test_only_admins_can_manage_roles(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]), ['*']);

        $this->postJson('/api/v1/roles', ['name' => 'sneaky', 'display_name' => 'Sneaky'])->assertForbidden();
    }
}
