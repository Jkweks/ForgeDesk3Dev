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

        foreach (config('navigation') as $section) {
            $this->assertSame(
                2,
                substr_count($html, 'data-nav-permission="'.$section['permission'].'"'),
                "{$section['label']} should render once in the top bar and once in the sidebar"
            );
        }

        // Duplicate ids would break getElementById-based scripts across the two navs.
        foreach (['id="navbar-menu"', 'id="sidebar-menu"', 'id="offcanvasTheme"'] as $id) {
            $this->assertSame(1, substr_count($html, $id), "{$id} must be unique");
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
            'theme-base' => 'gray',
        ])->assertOk()->assertJsonPath('theme_preferences.navbar-position', 'vertical');

        // Full replace: a payload with only defaults-omitted keys drops the rest.
        $this->putJson('/api/v1/user/theme-preferences', ['theme' => 'dark'])
            ->assertOk()->assertExactJson(['theme_preferences' => ['theme' => 'dark']]);

        $this->putJson('/api/v1/user/theme-preferences', [])
            ->assertOk()->assertExactJson(['theme_preferences' => null]);
    }

    public function test_theme_preferences_reject_unknown_values(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);

        foreach ([['navbar-position' => 'left'], ['sidebar' => 'huge'], ['layout' => 'wide'], ['theme' => 'sepia']] as $payload) {
            $this->putJson('/api/v1/user/theme-preferences', $payload)->assertStatus(422);
        }
    }
}
