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

        $response = $this->getJson('/api/v1/dashboard/layout')->assertOk()->assertJsonPath('source', 'built-in');
        $widgets = $response->json('layout.widgets');

        $this->assertCount(15, $widgets);
        $this->assertSame('wo_open', $widgets[0]['key']);
        $this->assertSame([0, 0], [$widgets[0]['x'], $widgets[0]['y']]);
    }

    public function test_built_in_layout_fits_the_grid_without_overlaps_and_only_uses_real_widgets(): void
    {
        $widgets = WidgetRegistry::builtInLayout()['widgets']; // no user: everything
        $catalog = WidgetRegistry::all();

        foreach ($widgets as $w) {
            $this->assertArrayHasKey($w['key'], $catalog);
            $this->assertLessThanOrEqual(WidgetRegistry::GRID_COLUMNS, $w['x'] + $w['w'], "{$w['key']} runs off the grid");
            $this->assertGreaterThanOrEqual($catalog[$w['key']]['min_size']['w'], $w['w'], "{$w['key']} narrower than its minimum");
            $this->assertGreaterThanOrEqual($catalog[$w['key']]['min_size']['h'], $w['h'], "{$w['key']} shorter than its minimum");
        }
        foreach ($widgets as $i => $a) {
            foreach (array_slice($widgets, $i + 1) as $b) {
                $overlap = $a['x'] < $b['x'] + $b['w'] && $b['x'] < $a['x'] + $a['w'] && $a['y'] < $b['y'] + $b['h'] && $b['y'] < $a['y'] + $a['h'];
                $this->assertFalse($overlap, "{$a['key']} overlaps {$b['key']}");
            }
        }
        $this->assertCount(count($widgets), array_unique(array_column($widgets, 'id')), 'widget ids must be unique');
    }

    public function test_built_in_layout_for_a_limited_role_is_repacked_without_holes(): void
    {
        $user = $this->actingAsRole('viewer');
        $visible = array_column(WidgetRegistry::forUser($user), 'key');

        $widgets = $this->getJson('/api/v1/dashboard/layout')->assertOk()->json('layout.widgets');

        foreach ($widgets as $w) {
            $this->assertContains($w['key'], $visible, "{$w['key']} is not visible to this role");
        }
        $this->assertLessThan(15, count($widgets));
        if ($widgets) {
            // Repacked: the first widget sits at the origin and each row starts at x=0 (no gap left by a hidden widget).
            $this->assertSame([0, 0], [$widgets[0]['x'], $widgets[0]['y']]);
            $rowStarts = collect($widgets)->groupBy('y')->map(fn ($row) => $row->min('x'));
            $this->assertSame([0], $rowStarts->unique()->values()->all());
        }

        // A role that sees nothing gets an empty (not broken) dashboard.
        $this->actingAsRole('no-such-role');
        $this->getJson('/api/v1/dashboard/layout')->assertOk()->assertJsonCount(0, 'layout.widgets');
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
