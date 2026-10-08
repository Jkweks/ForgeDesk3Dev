<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\FdUser;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Models\FdWoStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression coverage for the security review fixes: permission-gated
 * mutating routes, admin-account protection, and the kiosk override token.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    /** Routes that are deliberately open to any signed-in user or unauthenticated kiosk/login traffic. */
    private const ALLOWED_WITHOUT_PERMISSION = [
        'api/login', 'api/logout', 'api/password/forgot', 'api/password/reset', 'api/password/verify-token',
        'api/v1/user/change-password', 'api/v1/user/profile', 'api/v1/user/theme-preferences',
        'api/v1/user/wo-column-prefs', 'api/v1/user/jobs-column-prefs', 'api/v1/user/quality-report-prefs',
        'api/v1/dashboard/layout', 'api/v1/notifications/{notification}/dismiss',
    ];

    public function test_every_mutating_api_route_has_a_permission_gate(): void
    {
        $missing = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }
            $methods = array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']);
            if (! $methods || in_array($uri, self::ALLOWED_WITHOUT_PERMISSION, true) || str_starts_with($uri, 'api/v1/shop/')) {
                continue;
            }
            $hasGate = collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && (str_contains($m, 'CheckPermission') || str_starts_with($m, 'permission:')));
            if (! $hasGate) {
                $missing[] = implode('|', $methods).' '.$uri;
            }
        }

        $this->assertSame([], $missing, "Mutating routes without a permission gate:\n".implode("\n", $missing));
    }

    public function test_zero_permission_user_cannot_touch_stock_or_bulk_data(): void
    {
        $user = User::factory()->create(['role' => 'retail', 'is_active' => true]);
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/import/products')->assertForbidden();
        $this->postJson('/api/v1/categories/bulk-action', [])->assertForbidden();
        $supplier = \App\Models\Supplier::create(['name' => 'Sec Supplier']);
        $product = \App\Models\Product::create(['sku' => 'SEC-1', 'description' => 'Test', 'supplier_id' => $supplier->id]);
        $this->postJson("/api/v1/products/{$product->id}/issue-to-job", [])->assertForbidden();
        $this->postJson("/api/v1/products/{$product->id}/locations", [])->assertForbidden();
        $this->postJson("/api/v1/products/{$product->id}/photo", [])->assertForbidden();
        $this->postJson('/api/v1/ez-estimate/upload')->assertForbidden();
    }

    public function test_material_check_requires_login(): void
    {
        $this->postJson('/api/v1/fulfillment/material-check')->assertUnauthorized();
        $this->postJson('/api/v1/fulfillment/material-check-csv')->assertUnauthorized();
    }

    public function test_non_admin_cannot_change_or_reset_an_admin_account(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $before = $admin->password;
        Sanctum::actingAs($manager, ['*']);

        $this->putJson("/api/v1/users/{$admin->id}", ['password' => 'new-password-1'])->assertForbidden();
        $this->postJson("/api/v1/users/{$admin->id}/reset-password", ['password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertForbidden();
        $this->deleteJson("/api/v1/users/{$admin->id}")->assertForbidden();
        $this->postJson("/api/v1/users/{$admin->id}/resend-invitation")->assertForbidden();

        $this->assertSame($before, $admin->fresh()->password);
        $this->assertNull($admin->fresh()->deleted_at);
    }

    public function test_admin_set_password_is_temporary(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'viewer', 'is_active' => true, 'must_change_password' => false]);
        Sanctum::actingAs($admin, ['*']);

        $this->putJson("/api/v1/users/{$target->id}", ['password' => 'new-password-1'])->assertOk();

        $target->refresh();
        $this->assertTrue($target->must_change_password);
        $this->assertTrue(Hash::check('new-password-1', $target->password));
    }

    public function test_forgot_password_does_not_reveal_inactive_accounts(): void
    {
        $inactive = User::factory()->create(['is_active' => false]);

        $known = $this->postJson('/api/password/forgot', ['email' => $inactive->email]);
        $unknown = $this->postJson('/api/password/forgot', ['email' => 'nobody@example.com']);

        $known->assertOk();
        $this->assertSame($unknown->json(), $known->json());
    }

    public function test_reset_clears_temporary_password_state(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => true,
            'password_set_at' => now()->subDays(30),
        ]);
        $token = 'reset-token-123';
        \DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => Hash::make($token), 'created_at' => now()]);

        $this->postJson('/api/password/reset', [
            'email' => $user->email, 'token' => $token,
            'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertOk();

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertFalse($user->temporaryPasswordExpired());
    }

    public function test_kiosk_override_needs_the_pin_issued_token(): void
    {
        $job = BusinessJob::create(['job_number' => 'J-1', 'job_name' => 'Job', 'status' => 'active']);
        $wo = FdWorkOrder::create(['business_job_id' => $job->id, 'release_number' => 1]);
        $elev = FdWoElevation::create(['work_order_id' => $wo->id, 'elevation_tag' => 'E1']);
        $first = FdWoStage::create(['elevation_id' => $elev->id, 'name' => 'Cut', 'sort_order' => 1, 'blocks_next' => true, 'status' => 'pending']);
        $second = FdWoStage::create(['elevation_id' => $elev->id, 'name' => 'Weld', 'sort_order' => 2, 'blocks_next' => true, 'status' => 'pending']);
        $manager = FdUser::create(['name' => 'Mgr', 'initials' => 'MG', 'role' => 'manager', 'active' => true, 'fab_pin' => Hash::make('4321')]);

        // Knowing the manager's (public) id is not enough to override the gate.
        $this->patchJson("/api/v1/shop/stages/{$second->id}", ['fab_user_id' => $manager->id, 'override' => true])
            ->assertStatus(422);
        $this->assertSame('pending', $second->fresh()->status);

        $login = $this->postJson('/api/v1/shop/fab-pin-login', ['pin' => '4321'])->assertOk();
        $token = $login->json('kiosk_token');
        $this->assertNotEmpty($token);

        $this->withHeaders(['X-Kiosk-Token' => $token])
            ->patchJson("/api/v1/shop/stages/{$second->id}", ['fab_user_id' => $manager->id, 'override' => true])
            ->assertOk();
    }
}
