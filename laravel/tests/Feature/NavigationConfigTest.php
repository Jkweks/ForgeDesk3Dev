<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards the nav reorganization: items moved between sections must keep the `nav.*` permission
 * they had before, and no page may become unreachable from the menu.
 */
class NavigationConfigTest extends TestCase
{
    /** @return array<int, array{section: array, item: array}> */
    private function items(): array
    {
        $out = [];
        foreach (config('navigation') as $section) {
            foreach ($section['items'] ?? [] as $item) {
                if (empty($item['divider']) && empty($item['header'])) {
                    $out[] = ['section' => $section, 'item' => $item];
                }
            }
        }

        return $out;
    }

    /** Sections with several `nav.*` permissions must say which one each item needs, and it must be one of them. */
    public function test_items_in_multi_permission_sections_declare_a_nav_permission_the_section_allows(): void
    {
        foreach ($this->items() as ['section' => $section, 'item' => $item]) {
            $permissions = (array) $section['permission'];
            if (count($permissions) < 2) {
                continue;
            }
            $this->assertArrayHasKey('nav', $item, "{$item['label']} in {$section['label']} needs a 'nav' permission");
            $this->assertContains($item['nav'], $permissions, "{$item['label']} would be unreachable: its nav permission is not one of the section's");
        }
    }

    /** Who sees each item must be exactly what it was before the reorganization. */
    public function test_each_items_effective_nav_permission_is_unchanged_by_the_reorganization(): void
    {
        $before = [
            '/inventory/products' => 'nav.inventory', '/categories' => 'nav.inventory', '/suppliers' => 'nav.inventory',
            '/low-stock' => 'nav.inventory', '/critical-stock' => 'nav.inventory', '/transactions' => 'nav.inventory',
            '/operations/replenishment' => 'nav.operations', '/purchase-orders' => 'nav.operations',
            '/cycle-counting' => 'nav.operations', '/storage-locations' => 'nav.operations',
            '/jobs' => 'nav.fulfillment', '/fulfillment/material-check' => 'nav.fulfillment', '/fulfillment/job-reservations' => 'nav.fulfillment',
            'http://fab.vosglassintra.net/configurator' => 'nav.fulfillment',
            '/config' => 'nav.configurator', '/config/package' => 'nav.configurator', '/config/labels' => 'nav.configurator', '/config/admin' => 'nav.configurator',
            '/fabrication/work-orders' => 'nav.fabrication', '/fabrication/work-queue' => 'nav.fabrication', '/fabrication/cut-lists' => 'nav.fabrication',
            '/fabrication/quality' => 'nav.fabrication', '/shop' => 'nav.fabrication', '/cut-station' => 'nav.fabrication',
            '/maintenance' => 'nav.maintenance',
        ];

        $effective = [];
        foreach ($this->items() as ['section' => $section, 'item' => $item]) {
            $effective[$item['href']] = $item['nav'] ?? $section['permission'];
        }

        foreach ($before as $href => $permission) {
            $this->assertSame($permission, $effective[$href] ?? null, "{$href} must still require {$permission}");
        }
    }

    public function test_pages_that_used_to_be_url_only_or_buried_are_in_the_menu(): void
    {
        $hrefs = collect(config('navigation'))
            ->flatMap(fn ($s) => array_merge([$s['href'] ?? null], array_column($s['items'] ?? [], 'href')))
            ->filter()->all();

        foreach (['/fabrication/documents', '/admin/location-assignment', '/inventory/products', '/reports', '/admin'] as $page) {
            $this->assertContains($page, $hrefs, "{$page} should be reachable from the nav");
        }
    }

    public function test_the_operations_section_is_gone_and_groups_render_as_headings(): void
    {
        $labels = array_column(config('navigation'), 'label');
        $this->assertNotContains('Operations', $labels);
        $this->assertSame(['Dashboard', 'Inventory', 'Jobs', 'Fabrication', 'Configurator', 'Maintenance', 'Reports', 'Admin'], $labels);

        $html = $this->get('/')->assertOk()->getContent();
        foreach (['Catalog', 'Stock', 'Activity', 'Purchasing', 'Displays'] as $heading) {
            $this->assertStringContainsString('<h6 class="dropdown-header">'.$heading.'</h6>', $html);
        }
        $this->assertStringContainsString('href="/fabrication/documents"', $html);
    }

    public function test_every_active_pattern_in_the_menu_matches_a_real_page(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->map(fn ($r) => $r->uri())->all();
        foreach ($this->items() as ['item' => $item]) {
            $href = parse_url($item['href'], PHP_URL_PATH);
            if (str_starts_with((string) $item['href'], 'http')) {
                continue;
            }
            $this->assertContains(ltrim($href, '/'), array_map(fn ($u) => ltrim($u, '/'), $routes) + [''], "{$item['label']} links to {$href}, which is not a route");
        }
    }

    /** The "Original" menu option must keep the pre-reorganization structure exactly. */
    public function test_the_original_menu_is_preserved_as_an_option(): void
    {
        $classic = config('navigation_classic');

        $this->assertSame(
            ['Dashboard', 'Inventory', 'Operations', 'Fulfillment', 'Configurator', 'Reports', 'Maintenance', 'Fabrication', 'Admin'],
            array_column($classic, 'label')
        );
        $byLabel = collect($classic)->keyBy('label');
        $this->assertSame(['Replenishment', 'Purchase Orders', 'Cycle Counting', 'Storage Locations'],
            array_values(array_filter(array_column($byLabel['Operations']['items'], 'label'))));
        $this->assertSame('nav.operations', $byLabel['Operations']['permission']);
        $this->assertSame('/inventory/products', $byLabel['Inventory']['items'][0]['href']);
        // Original really was original: no group headings, no per-item nav overrides.
        foreach ($classic as $section) {
            foreach ($section['items'] ?? [] as $item) {
                $this->assertArrayNotHasKey('header', $item);
                $this->assertArrayNotHasKey('nav', $item);
            }
        }
    }

    public function test_the_customize_panel_offers_the_menu_organization_choice(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('name="nav-menu" value="classic"', $html);
        $this->assertStringContainsString('name="nav-menu" value="default"', $html);
        $this->assertStringContainsString('html[data-bs-nav-menu=classic] [data-nav-set=new]', $html);
        $this->assertStringContainsString("localStorage.getItem('tabler-nav-menu')", $html);
    }

    public function test_renamed_links_use_their_new_names_in_both_menus(): void
    {
        foreach (['navigation', 'navigation_classic'] as $configKey) {
            $labels = collect(config($configKey))->flatMap(fn ($s) => array_column($s['items'] ?? [], 'label'))->all();
            foreach (['Entry Builder', 'Cut Flow'] as $name) {
                $this->assertContains($name, $labels, "{$name} missing from {$configKey}");
            }
            foreach (['Frame Builder', 'Cut Station'] as $old) {
                $this->assertNotContains($old, $labels, "{$old} should have been renamed in {$configKey}");
            }
        }

        $this->get('/config')->assertOk()->assertSee('<h1 class="page-title">Entry Builder</h1>', false);
    }
}
