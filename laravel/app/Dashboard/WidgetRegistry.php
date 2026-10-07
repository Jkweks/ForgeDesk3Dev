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
 *   stat  — one number: `field` read from the `endpoint` JSON, optional `link`.
 *   list  — `endpoint` returns {items:[{label, sub, meta, meta_class, link}]}.
 *   table — `endpoint` returns {columns:[{key,label,align?}], rows:[{link, cells}], total};
 *           a cell is a string or {text, class} (rendered as a badge).
 *   chart — Chart.js chart named by `chart`; the renderer in the JS knows each
 *           chart's endpoint shape.
 */
class WidgetRegistry
{
    public const LAYOUT_VERSION = 1;

    public const GRID_COLUMNS = 12;

    /** @return array<string, array<string, mixed>> keyed by widget key */
    public static function all(): array
    {
        $stat = fn (string $key, string $title, string $description, string $icon, string $field, string $link) => [
            'key' => $key, 'title' => $title, 'description' => $description, 'icon' => $icon,
            'type' => 'stat', 'field' => $field, 'link' => $link,
            'default_size' => ['w' => 3, 'h' => 2], 'min_size' => ['w' => 2, 'h' => 2],
            'refresh_seconds' => 120,
        ];
        $chart = fn (string $key, string $title, string $description, string $icon, string $chart) => [
            'key' => $key, 'title' => $title, 'description' => $description, 'icon' => $icon,
            'type' => 'chart', 'chart' => $chart,
            'default_size' => ['w' => 6, 'h' => 5], 'min_size' => ['w' => 3, 'h' => 3],
            'refresh_seconds' => 600,
        ];
        $table = fn (string $key, string $title, string $description, string $icon) => [
            'key' => $key, 'title' => $title, 'description' => $description, 'icon' => $icon,
            'type' => 'table',
            'default_size' => ['w' => 8, 'h' => 6], 'min_size' => ['w' => 4, 'h' => 3],
            'refresh_seconds' => 120,
        ];
        $list = fn (string $key, string $title, string $description, string $icon) => [
            'key' => $key, 'title' => $title, 'description' => $description, 'icon' => $icon,
            'type' => 'list',
            'default_size' => ['w' => 4, 'h' => 5], 'min_size' => ['w' => 3, 'h' => 3],
            'refresh_seconds' => 120,
        ];

        $groups = [
            ['Inventory', ['inventory.view'], '/dashboard/stats', [
                $stat('inventory_skus', 'SKUs Tracked', 'Active products in inventory.', 'ti-box', 'skus_tracked', '/inventory/products'),
                $stat('inventory_on_hand', 'Units On Hand', 'Total units across all locations.', 'ti-packages', 'units_on_hand', '/inventory/products'),
                $stat('inventory_available', 'Units Available', 'On hand minus units committed to jobs.', 'ti-circle-check', 'units_available', '/inventory/products'),
                $stat('inventory_low_stock', 'Low Stock Alerts', 'Products at or below their low/critical thresholds.', 'ti-alert-triangle', 'low_stock_alerts', '/low-stock'),
                $stat('inventory_critical', 'Critical Stock', 'Products at critical stock level.', 'ti-alert-octagon', 'critical_count', '/critical-stock'),
            ]],
            ['Work Orders', ['fabrication.work-orders.view'], '/dashboard/widgets/work-orders', [
                $stat('wo_open', 'Open Work Orders', 'Active and on-hold work orders.', 'ti-clipboard-list', 'open', '/fabrication/work-orders'),
                $stat('wo_overdue', 'Overdue Work Orders', 'Open work orders past their due date.', 'ti-clock-exclamation', 'overdue_count', '/fabrication/work-orders'),
                $stat('wo_due_week', 'Due This Week', 'Open work orders due within 7 days.', 'ti-calendar-due', 'due_this_week', '/fabrication/work-orders'),
                $stat('wo_on_hold', 'On Hold', 'Work orders currently on hold.', 'ti-player-pause', 'on_hold_count', '/fabrication/work-orders'),
            ]],
            ['Work Orders', ['fabrication.work-orders.view'], '/dashboard/widgets/work-orders/due', [
                $list('wo_due_list', 'Work Orders Due Soon', 'Open work orders with the earliest due dates, overdue first.', 'ti-list-details'),
            ]],
            ['Work Orders', ['fabrication.work-orders.view'], '/dashboard/widgets/work-orders/table', [
                $table('wo_table', 'Work Order Table', 'Open work orders in priority order, with job, status, due date and elevation progress.', 'ti-table'),
            ]],
            ['Work Orders', ['fabrication.work-orders.view'], '/dashboard/widgets/work-orders/stages', [
                $chart('wo_stage_wip', 'Work in Progress by Stage', 'Open pending and in-progress stages, grouped by stage name.', 'ti-chart-bar', 'stage_wip'),
            ]],
            ['Quality', ['quality.view'], '/dashboard/widgets/quality', [
                $stat('quality_pending', 'Reports Pending Verification', 'Quality reports waiting to be verified.', 'ti-file-search', 'pending_review', '/fabrication/quality'),
                $stat('quality_awaiting_review', 'Reports Awaiting Review', 'Verified quality reports waiting for review.', 'ti-file-check', 'verified', '/fabrication/quality'),
            ]],
            ['Quality', ['quality.view'], '/quality-reports/analytics/incident-rate', [
                $chart('quality_incident_rate', 'Incident Rate by Month', 'Joints completed and incident rate, with the 1.5% goal.', 'ti-chart-line', 'incident_rate'),
            ]],
            ['Quality', ['quality.view'], '/quality-reports/analytics/problem-types', [
                $chart('quality_problem_types', 'Problem Types', 'Quality cases by problem type over the last 13 weeks.', 'ti-chart-bar', 'problem_types'),
            ]],
            ['Quality', ['quality.view'], '/quality-reports/analytics/weekly-trend', [
                $chart('quality_weekly_trend', 'Weekly Case Trend', 'Quality cases per week with a trend line.', 'ti-trending-up', 'weekly_trend'),
            ]],
        ];

        $catalog = [];
        foreach ($groups as [$category, $permission, $endpoint, $widgets]) {
            foreach ($widgets as $w) {
                $catalog[$w['key']] = $w + ['category' => $category, 'permission' => $permission, 'endpoint' => $endpoint];
            }
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
