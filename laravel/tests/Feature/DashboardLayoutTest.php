<?php

namespace Tests\Feature;

use App\Dashboard\WidgetRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function layout(array $widgets): array
    {
        return ['widgets' => $widgets];
    }

    private function widget(string $key = 'inventory_skus', array $overrides = []): array
    {
        return array_merge(['id' => $key, 'key' => $key, 'x' => 0, 'y' => 0, 'w' => 3, 'h' => 2], $overrides);
    }

    public function test_admin_sees_full_catalog(): void
    {
        $this->actingAsRole('admin');

        $keys = collect($this->getJson('/api/v1/dashboard/widgets')->assertOk()->json('widgets'))->pluck('key');

        $this->assertEqualsCanonicalizing(array_keys(WidgetRegistry::all()), $keys->all());
    }

    public function test_user_without_permission_gets_no_inventory_widgets(): void
    {
        $user = $this->actingAsRole('viewer');
        // Roles with no inventory.view: simulate by using a role name that has no Role row.
        $user->update(['role' => 'no-such-role']);

        $this->getJson('/api/v1/dashboard/widgets')->assertOk()->assertJsonCount(0, 'widgets');
        $this->getJson('/api/v1/dashboard/layout')->assertOk()->assertJsonCount(0, 'layout.widgets');
    }

    public function test_default_layout_is_built_in_until_customized(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/api/v1/dashboard/layout')
            ->assertOk()
            ->assertJsonPath('source', 'built-in')
            ->assertJsonCount(4, 'layout.widgets');
    }

    public function test_user_can_save_and_reset_their_layout(): void
    {
        $this->actingAsRole('admin');

        $this->putJson('/api/v1/dashboard/layout', $this->layout([$this->widget('inventory_critical', ['x' => 4])]))
            ->assertOk()->assertJsonPath('source', 'user');

        $this->getJson('/api/v1/dashboard/layout')
            ->assertJsonPath('source', 'user')
            ->assertJsonCount(1, 'layout.widgets')
            ->assertJsonPath('layout.widgets.0.x', 4);

        $this->deleteJson('/api/v1/dashboard/layout')->assertOk()->assertJsonPath('source', 'built-in');
    }

    public function test_layout_validation_rejects_unknown_widget_duplicate_ids_and_bad_geometry(): void
    {
        $this->actingAsRole('admin');

        $this->putJson('/api/v1/dashboard/layout', $this->layout([$this->widget('nope')]))->assertStatus(422);
        $this->putJson('/api/v1/dashboard/layout', $this->layout([$this->widget(), $this->widget()]))->assertStatus(422);
        $this->putJson('/api/v1/dashboard/layout', $this->layout([$this->widget('inventory_skus', ['w' => 99])]))->assertStatus(422);
        $this->putJson('/api/v1/dashboard/layout', ['widgets' => 'x'])->assertStatus(422);
    }

    public function test_same_widget_can_appear_twice_with_distinct_ids(): void
    {
        $this->actingAsRole('admin');

        $this->putJson('/api/v1/dashboard/layout', $this->layout([
            $this->widget('inventory_skus', ['id' => 'a']),
            $this->widget('inventory_skus', ['id' => 'b', 'x' => 3]),
        ]))->assertOk()->assertJsonCount(2, 'layout.widgets');
    }

    public function test_company_default_is_admin_only_and_used_by_other_users(): void
    {
        $this->actingAsRole('viewer');
        $this->putJson('/api/v1/dashboard/default-layout', $this->layout([$this->widget()]))->assertForbidden();

        $this->actingAsRole('admin');
        $this->putJson('/api/v1/dashboard/default-layout', $this->layout([$this->widget('inventory_critical')]))
            ->assertOk()->assertJsonPath('source', 'default');

        $this->actingAsRole('manager');
        $this->getJson('/api/v1/dashboard/layout')
            ->assertJsonPath('source', 'default')
            ->assertJsonPath('layout.widgets.0.key', 'inventory_critical');

        $this->actingAsRole('admin');
        $this->putJson('/api/v1/dashboard/default-layout', ['widgets' => null])
            ->assertOk()->assertJsonPath('source', 'built-in');
    }

    public function test_dashboard_stats_requires_inventory_view(): void
    {
        $user = $this->actingAsRole('viewer');
        $user->update(['role' => 'no-such-role']);

        $this->getJson('/api/v1/dashboard/stats')->assertForbidden();

        $this->actingAsRole('admin');
        $this->getJson('/api/v1/dashboard/stats')->assertOk()->assertJsonStructure(['skus_tracked', 'units_on_hand', 'low_stock_alerts']);
    }
}
