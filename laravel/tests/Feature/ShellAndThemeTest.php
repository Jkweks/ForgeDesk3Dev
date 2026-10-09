<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShellAndThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_shell_renders_both_navigations_with_every_permission_key_twice(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('navbar-vertical', $html);
        $this->assertStringContainsString('id="navbar-menu"', $html);

        // Both menu structures are rendered (CSS shows the chosen one). Within each, every section
        // appears once in the top bar and once in the sidebar.
        foreach (['new' => 'navigation', 'classic' => 'navigation_classic'] as $set => $configKey) {
            preg_match_all('#<div data-nav-set="'.$set.'">.*?</ul>#s', $html, $blocks);
            $this->assertCount(2, $blocks[0], "the {$set} menu renders in the top bar and the sidebar");
            $joined = implode("\n", $blocks[0]);

            $sharing = array_count_values(array_map(fn ($s) => implode(' ', (array) $s['permission']), config($configKey)));
            foreach (config($configKey) as $section) {
                $attr = preg_quote(implode(' ', (array) $section['permission']), '/');
                // Sections may share a permission (Quality keeps Fabrication's); each still renders twice.
                $this->assertSame(
                    2 * $sharing[implode(' ', (array) $section['permission'])],
                    preg_match_all('/<li class="nav-item[^"]*" data-nav-permission="'.$attr.'"/', $joined),
                    "{$section['label']} ({$set}) should render once in the top bar and once in the sidebar"
                );
            }
        }

        // Sidebar user text must live in .nav-link-title so Tabler collapses it when the sidebar folds.
        $this->assertMatchesRegularExpression('/nav-link-title[^"]*">\s*<div class="js-user-name"/', $html);

        // Duplicate ids would break getElementById-based scripts across the two navs.
        foreach (['id="navbar-menu"', 'id="sidebar-menu"', 'id="offcanvasTheme"'] as $id) {
            $this->assertSame(1, substr_count($html, $id), "{$id} must be unique");
        }
    }

    public function test_dashboard_and_all_products_are_separate_pages_with_working_nav_link(): void
    {
        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('id="dashboardGrid"', $home);
        $this->assertStringContainsString('href="/inventory/products"', $home);

        $products = $this->get('/inventory/products')->assertOk()->getContent();
        $this->assertStringContainsString('All Products', $products);
        $this->assertStringNotContainsString('id="dashboardGrid"', $products);
        // The theme panel lives in the layout only; the products page must not carry a second copy.
        $this->assertSame(1, substr_count($products, 'id="offcanvasTheme"'));
    }

    public function test_shop_floor_stage_colors_come_from_theme_tokens_not_hex_pairs(): void
    {
        $html = $this->get('/shop')->assertOk()->getContent();

        $this->assertStringContainsString('--sf-c', $html);
        $this->assertStringContainsString('tabler-themes.min.css', $html);
        foreach (['#fff3cd', '#d1e7dd', '#f8d7da', '#3d2e00'] as $legacyHex) {
            $this->assertStringNotContainsString($legacyHex, $html);
        }
    }

    public function test_every_page_view_that_extends_the_layout_provides_a_page_wrapper(): void
    {
        $missing = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            if (str_contains($src, "@extends('layouts.app')") && ! str_contains($src, 'page-wrapper')) {
                $missing[] = $file->getPathname();
            }
        }

        $this->assertSame([], $missing, 'Views need .page-wrapper so the sidebar layout offsets them');
    }

    public function test_theme_preferences_accept_new_layout_keys_and_replace_the_blob(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($user, ['*']);

        $this->putJson('/api/v1/user/theme-preferences', [
            'theme' => 'auto',
            'navbar-position' => 'vertical',
            'sidebar' => 'folded-hover',
            'layout' => 'boxed',
            'navbar' => 'sticky',
            'navbar-theme' => 'dark',
            'nav-menu' => 'classic',
            'theme-base' => 'gray',
        ])->assertOk()->assertJsonPath('theme_preferences.navbar-position', 'vertical');

        // Full replace: a payload with only defaults-omitted keys drops the rest.
        $this->putJson('/api/v1/user/theme-preferences', ['theme' => 'dark'])
            ->assertOk()->assertExactJson(['theme_preferences' => ['theme' => 'dark']]);

        $this->putJson('/api/v1/user/theme-preferences', [])
            ->assertOk()->assertExactJson(['theme_preferences' => null]);
    }

    public function test_customize_panel_renders_a_preview_tile_for_every_option(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Tile controls carry an inline SVG preview; spot-check a few, incl. the pinned gray-scale attr.
        $this->assertStringContainsString('form-imagecheck-image', $html);
        $this->assertStringContainsString('data-bs-theme-base="stone"', $html);
        foreach (['navbar-position', 'sidebar', 'navbar', 'navbar-theme', 'layout', 'theme-radius'] as $key) {
            $this->assertStringContainsString('name="'.$key.'"', $html);
        }
    }

    public function test_theme_preferences_reject_unknown_values(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);

        foreach ([['navbar-position' => 'left'], ['sidebar' => 'huge'], ['layout' => 'wide'], ['theme' => 'sepia'], ['navbar-theme' => 'neon'], ['nav-menu' => 'compact']] as $payload) {
            $this->putJson('/api/v1/user/theme-preferences', $payload)->assertStatus(422);
        }
    }
}
