<?php

namespace App\Dashboard;

use App\Models\CompanySetting;
use App\Models\User;

/**
 * Catalog of dashboard widgets ("modules") a user can place on their
 * dashboard, plus layout resolution (user layout -> company default ->
 * built-in default) filtered to what the user is allowed to see.
 *
 * Every widget names the permission(s) required to see it AND to call its
 * data endpoint; the endpoint's route must carry the matching `permission:`
 * middleware, since hiding a widget in the UI is not access control.
 *
 * Widget `type`s understood by public/js/dashboard-widgets.js:
 *   stat — one number: `field` read from the `endpoint` JSON, optional `link`.
 */
class WidgetRegistry
{
    public const LAYOUT_VERSION = 1;

    public const GRID_COLUMNS = 12;

    /** @return array<string, array<string, mixed>> keyed by widget key */
    public static function all(): array
    {
        $widgets = [
            ['key' => 'inventory_skus', 'title' => 'SKUs Tracked', 'description' => 'Active products in inventory.', 'icon' => 'ti-box', 'field' => 'skus_tracked', 'link' => '/inventory/products'],
            ['key' => 'inventory_on_hand', 'title' => 'Units On Hand', 'description' => 'Total units across all locations.', 'icon' => 'ti-packages', 'field' => 'units_on_hand', 'link' => '/inventory/products'],
            ['key' => 'inventory_available', 'title' => 'Units Available', 'description' => 'On hand minus units committed to jobs.', 'icon' => 'ti-circle-check', 'field' => 'units_available', 'link' => '/inventory/products'],
            ['key' => 'inventory_low_stock', 'title' => 'Low Stock Alerts', 'description' => 'Products at or below their low/critical thresholds.', 'icon' => 'ti-alert-triangle', 'field' => 'low_stock_alerts', 'link' => '/low-stock'],
            ['key' => 'inventory_critical', 'title' => 'Critical Stock', 'description' => 'Products at critical stock level.', 'icon' => 'ti-alert-octagon', 'field' => 'critical_count', 'link' => '/critical-stock'],
        ];

        $catalog = [];
        foreach ($widgets as $w) {
            $catalog[$w['key']] = $w + [
                'category' => 'Inventory',
                'type' => 'stat',
                'permission' => ['inventory.view'],
                'endpoint' => '/dashboard/stats',
                'default_size' => ['w' => 3, 'h' => 2],
                'min_size' => ['w' => 2, 'h' => 2],
                'refresh_seconds' => 120,
            ];
        }

        return $catalog;
    }

    public static function find(string $key): ?array
    {
        return static::all()[$key] ?? null;
    }

    public static function canSee(User $user, array $widget): bool
    {
        return $user->hasAnyPermission($widget['permission']);
    }

    /** Catalog entries the user may add/see, in registry order. */
    public static function forUser(User $user): array
    {
        return array_values(array_filter(
            static::all(),
            fn ($widget) => static::canSee($user, $widget)
        ));
    }

    /** Layout shipped in code: the inventory summary row that replaced the old home page's stat cards. */
    public static function builtInLayout(): array
    {
        $widgets = [];
        foreach (['inventory_skus', 'inventory_on_hand', 'inventory_available', 'inventory_low_stock'] as $i => $key) {
            $widgets[] = ['id' => $key, 'key' => $key, 'x' => $i * 3, 'y' => 0, 'w' => 3, 'h' => 2, 'settings' => (object) []];
        }

        return ['version' => static::LAYOUT_VERSION, 'widgets' => $widgets];
    }

    /**
     * The layout to render for $user: their saved layout, else the company
     * default, else the built-in one — minus widgets that no longer exist or
     * that the user can't see. Returns [layout, source].
     */
    public static function resolveLayout(User $user): array
    {
        if (is_array($user->dashboard_prefs) && isset($user->dashboard_prefs['widgets'])) {
            $layout = $user->dashboard_prefs;
            $source = 'user';
        } elseif (is_array($default = CompanySetting::current()->dashboard_default_layout) && isset($default['widgets'])) {
            $layout = $default;
            $source = 'default';
        } else {
            $layout = static::builtInLayout();
            $source = 'built-in';
        }

        $layout['widgets'] = static::filterWidgets($user, $layout['widgets']);

        return [$layout, $source];
    }

    /** Drop entries whose key is unknown or not permitted for $user. */
    public static function filterWidgets(User $user, array $widgets): array
    {
        $catalog = static::all();

        return array_values(array_filter(
            $widgets,
            fn ($w) => isset($catalog[$w['key'] ?? '']) && static::canSee($user, $catalog[$w['key']])
        ));
    }
}
