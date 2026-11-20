<?php

namespace Tests\Feature;

use App\Models\FdUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FabUserPinTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => $role, 'is_active' => true]), ['*']);
    }

    public function test_setting_a_pin_keeps_the_hash_for_sign_in_and_stores_a_viewable_encrypted_copy(): void
    {
        $this->as('admin');
        $u = FdUser::create(['name' => 'Sam Saw', 'role' => 'worker', 'active' => true]);

        $this->postJson("/api/v1/fab-users/{$u->id}/set-pin", ['pin' => '4821'])->assertOk();
        $u->refresh();
        $this->assertTrue(Hash::check('4821', $u->fab_pin), 'sign-in still checks the bcrypt hash');
        $this->assertNotSame('4821', $u->fab_pin_enc);
        $this->assertStringNotContainsString('4821', (string) $u->fab_pin_enc, 'not stored in the clear');

        $this->getJson("/api/v1/fab-users/{$u->id}/pin")->assertOk()->assertJson(['pin' => '4821', 'has_pin' => true]);

        // the list never carries the PIN, only whether it can be shown
        $row = collect($this->getJson('/api/v1/fab-users?all=1')->json('users'))->firstWhere('id', $u->id);
        $this->assertTrue($row['has_pin']);
        $this->assertTrue($row['pin_viewable']);
        $this->assertArrayNotHasKey('fab_pin', $row);
        $this->assertArrayNotHasKey('fab_pin_enc', $row);
        $this->assertStringNotContainsString('4821', json_encode($row));
    }

    public function test_changing_and_clearing_the_pin_updates_both_copies(): void
    {
        $this->as('admin');
        $u = FdUser::create(['name' => 'Sam Saw', 'role' => 'worker', 'active' => true]);
        $this->postJson("/api/v1/fab-users/{$u->id}/set-pin", ['pin' => '1111'])->assertOk();
        $this->postJson("/api/v1/fab-users/{$u->id}/set-pin", ['pin' => '90210'])->assertOk();
        $this->getJson("/api/v1/fab-users/{$u->id}/pin")->assertJson(['pin' => '90210']);
        $this->assertTrue(Hash::check('90210', $u->fresh()->fab_pin));

        $this->postJson("/api/v1/fab-users/{$u->id}/set-pin", ['pin' => null])->assertOk();
        $u->refresh();
        $this->assertNull($u->fab_pin);
        $this->assertNull($u->fab_pin_enc);
        $this->getJson("/api/v1/fab-users/{$u->id}/pin")->assertJson(['pin' => null, 'has_pin' => false]);
    }

    public function test_a_pin_set_before_viewing_existed_is_hash_only_and_cannot_be_shown(): void
    {
        $this->as('admin');
        $u = FdUser::create(['name' => 'Old Pin', 'role' => 'worker', 'active' => true]);
        $u->forceFill(['fab_pin' => Hash::make('2468')])->save();   // as saved before the encrypted copy

        $row = collect($this->getJson('/api/v1/fab-users?all=1')->json('users'))->firstWhere('id', $u->id);
        $this->assertTrue($row['has_pin']);
        $this->assertFalse($row['pin_viewable']);
        $this->getJson("/api/v1/fab-users/{$u->id}/pin")->assertOk()->assertJson(['pin' => null, 'has_pin' => true]);
    }

    public function test_only_users_who_can_edit_users_may_view_a_pin(): void
    {
        $u = FdUser::create(['name' => 'Sam Saw', 'role' => 'worker', 'active' => true]);
        $this->as('admin');
        $this->postJson("/api/v1/fab-users/{$u->id}/set-pin", ['pin' => '4821'])->assertOk();

        $this->as('viewer');
        $this->getJson("/api/v1/fab-users/{$u->id}/pin")->assertForbidden();
    }

    public function test_permissions_chart_data_lists_every_permission_and_each_roles_grants(): void
    {
        $this->as('admin');
        $groups = $this->getJson('/api/v1/permissions')->assertOk()->json();
        $roles = $this->getJson('/api/v1/roles')->assertOk()->json();
        $names = collect($groups)->flatMap(fn ($g) => collect($g['permissions'])->pluck('name'));
        $this->assertGreaterThan(50, $names->count());
        $admin = collect($roles)->firstWhere('name', 'admin');
        $this->assertNotNull($admin);
        $this->assertEmpty($admin['permissions'] === [] ? ['none'] : []);
        $this->assertTrue(collect($admin['permissions'])->every(fn ($p) => $names->contains($p)));
    }
}
