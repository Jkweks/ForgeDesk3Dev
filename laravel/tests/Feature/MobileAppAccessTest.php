<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The "Allow mobile app" option on the user forms. It publishes the user's password hash to the
 * internet-facing field database (see FleetFieldSyncTest), so only administrators may grant it.
 */
class MobileAppAccessTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true, 'welcome_email_sent_at' => now()]), ['*']);
    }

    private function actAsUserManager(): void
    {
        $role = Role::firstOrCreate(['name' => 'user-manager'], ['display_name' => 'User manager']);
        foreach (['users.view', 'users.create', 'users.edit'] as $p) {
            $role->permissions()->syncWithoutDetaching(Permission::firstOrCreate(['name' => $p], ['display_name' => $p])->id);
        }
        Sanctum::actingAs(User::factory()->create(['role' => 'user-manager', 'is_active' => true]), ['*']);
    }

    private function newUser(array $over = []): array
    {
        return array_merge([
            'first_name' => 'Pat', 'last_name' => 'Crew', 'email' => 'pat@example.com', 'role' => 'viewer',
        ], $over);
    }

    public function test_admin_can_allow_mobile_access_when_creating_a_user(): void
    {
        Notification::fake();
        $this->actAsAdmin();

        $this->postJson('/api/v1/users', $this->newUser(['mobile_app_access' => true]))
            ->assertCreated()->assertJsonPath('user.mobile_app_access', true);

        $this->assertTrue(User::where('email', 'pat@example.com')->first()->mobile_app_access);
    }

    public function test_mobile_access_defaults_to_off(): void
    {
        Notification::fake();
        $this->actAsAdmin();

        $this->postJson('/api/v1/users', $this->newUser())->assertCreated();

        $this->assertFalse(User::where('email', 'pat@example.com')->first()->mobile_app_access);
    }

    public function test_admin_can_toggle_it_on_edit_and_the_list_reports_it(): void
    {
        $this->actAsAdmin();
        $user = User::factory()->create(['role' => 'viewer', 'is_active' => true]);

        $this->putJson("/api/v1/users/{$user->id}", ['mobile_app_access' => true])->assertOk();
        $this->assertTrue($user->fresh()->mobile_app_access);
        $row = collect($this->getJson('/api/v1/users')->json())->firstWhere('id', $user->id);
        $this->assertTrue($row['mobile_app_access']);

        $this->putJson("/api/v1/users/{$user->id}", ['mobile_app_access' => false])->assertOk();
        $this->assertFalse($user->fresh()->mobile_app_access);
    }

    public function test_non_admin_user_managers_cannot_grant_it_but_can_still_edit_users(): void
    {
        Notification::fake();
        $this->actAsUserManager();
        $user = User::factory()->create(['role' => 'viewer', 'is_active' => true]);

        $this->postJson('/api/v1/users', $this->newUser(['mobile_app_access' => true]))->assertForbidden();
        $this->putJson("/api/v1/users/{$user->id}", ['mobile_app_access' => true])->assertForbidden();
        $this->assertFalse($user->fresh()->mobile_app_access);

        // Everything else about the form keeps working, including re-saving an unchanged flag.
        $this->putJson("/api/v1/users/{$user->id}", ['first_name' => 'Renamed', 'mobile_app_access' => false])->assertOk();
        $this->assertSame('Renamed', $user->fresh()->first_name);
        $this->postJson('/api/v1/users', $this->newUser(['email' => 'other@example.com']))->assertCreated();
    }

    public function test_a_non_admin_can_revoke_access_but_not_grant_it_back(): void
    {
        $user = User::factory()->create(['role' => 'viewer', 'is_active' => true, 'mobile_app_access' => true]);
        $this->actAsUserManager();

        $this->putJson("/api/v1/users/{$user->id}", ['mobile_app_access' => false])->assertOk();
        $this->assertFalse($user->fresh()->mobile_app_access);
        $this->putJson("/api/v1/users/{$user->id}", ['mobile_app_access' => true])->assertForbidden();
    }
}
