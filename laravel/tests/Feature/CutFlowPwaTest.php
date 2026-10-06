<?php

namespace Tests\Feature;

use Tests\TestCase;

class CutFlowPwaTest extends TestCase
{
    public function test_manifest_is_scoped_to_cut_station(): void
    {
        $res = $this->get('/cut-station/manifest.webmanifest');

        $res->assertOk();
        $this->assertStringContainsString('application/manifest+json', $res->headers->get('Content-Type'));
        $json = $res->json();
        $this->assertSame('/cut-station', $json['scope']);
        $this->assertSame('/cut-station', $json['start_url']);
        $this->assertSame('standalone', $json['display']);
        $this->assertContains('maskable', array_column($json['icons'], 'purpose'));
    }

    public function test_service_worker_is_served_uncached_with_scope_header(): void
    {
        $res = $this->get('/cut-station/sw.js');

        $res->assertOk();
        $this->assertStringContainsString('text/javascript', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $res->headers->get('Cache-Control'));
        $this->assertSame('/cut-station', $res->headers->get('Service-Worker-Allowed'));
        $body = $res->getContent();
        $this->assertStringNotContainsString('__VERSION__', $body);
        $this->assertStringNotContainsString('__ICONS__', $body);
        $this->assertMatchesRegularExpression("/CACHE = 'cutflow-[0-9a-f]{10}'/", $body);
    }

    public function test_offline_page_renders(): void
    {
        $this->get('/cut-station/offline')->assertStatus(503)->assertSee('reach the server');
    }

    public function test_pwa_icons_exist(): void
    {
        foreach (['icon-192.png', 'icon-512.png', 'icon-maskable-512.png', 'apple-touch-icon.png'] as $f) {
            $this->assertFileExists(public_path("cutflow-pwa/{$f}"));
        }
    }

    public function test_legacy_cut_record_url_redirects_to_new_path(): void
    {
        $uuid = '11111111-2222-3333-4444-555555555555';

        $this->get("/cut-station/cuts/{$uuid}")->assertRedirect("/cut-record/{$uuid}")->assertStatus(301);
    }

    public function test_main_app_layout_has_no_pwa_wiring(): void
    {
        foreach (glob(resource_path('views/layouts/*.blade.php')) as $file) {
            $src = file_get_contents($file);
            $this->assertStringNotContainsString('rel="manifest"', $src, $file);
            $this->assertStringNotContainsString('serviceWorker', $src, $file);
        }
    }

    public function test_cut_record_page_is_not_pwa_enabled(): void
    {
        $layout = file_get_contents(resource_path('views/cutflow/cuts-show.blade.php'));
        $this->assertStringContainsString(':pwa="false"', $layout);
    }
}
