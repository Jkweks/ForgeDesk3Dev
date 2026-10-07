<?php

namespace App\Http\Controllers\CutFlow;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Installability for the cut-station kiosk only (docs/plans/cut-station-pwa.md).
 * Deliberately not offline-capable: every action is a Livewire round trip, so
 * the service worker only precaches a static "can't reach server" page.
 */
class PwaController extends Controller
{
    private const ICONS = [
        'icon-192.png',
        'icon-512.png',
        'icon-maskable-512.png',
        'apple-touch-icon.png',
    ];

    public function manifest(): Response
    {
        $icon = fn (string $file, string $sizes, string $purpose = 'any') => [
            'src' => "/cutflow-pwa/{$file}",
            'sizes' => $sizes,
            'type' => 'image/png',
            'purpose' => $purpose,
        ];

        $manifest = [
            'id' => '/cut-station',
            'name' => 'CutFlow',
            'short_name' => 'CutFlow',
            'start_url' => '/cut-station',
            'scope' => '/cut-station',
            'display' => 'standalone',
            'background_color' => '#EDEEF0',
            'theme_color' => '#22262B',
            'icons' => [
                $icon('icon-192.png', '192x192'),
                $icon('icon-512.png', '512x512'),
                $icon('icon-maskable-512.png', '512x512', 'maskable'),
            ],
        ];

        return response(json_encode($manifest, JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/manifest+json',
        ]);
    }

    public function serviceWorker(): Response
    {
        $source = file_get_contents(resource_path('js/cutflow-sw.js'));

        // Cache name changes whenever the worker or an icon changes, so
        // deployed updates replace the old shell instead of stranding it.
        $version = substr(md5($source.implode(',', array_map(
            fn ($f) => @filemtime(public_path("cutflow-pwa/{$f}")),
            self::ICONS
        ))), 0, 10);

        $source = str_replace(
            ['__VERSION__', '__ICONS__'],
            [$version, json_encode(array_map(fn ($f) => "/cutflow-pwa/{$f}", self::ICONS))],
            $source
        );

        return response($source, 200, [
            'Content-Type' => 'text/javascript',
            'Cache-Control' => 'no-cache',
            // Worker lives at /cut-station/sw.js but must control /cut-station
            // itself (no trailing slash), which is one level above its directory.
            'Service-Worker-Allowed' => '/cut-station',
        ]);
    }

    public function offline(): Response
    {
        // 503 so a stray direct visit isn't mistaken for a healthy page; the
        // service worker serves this body regardless of status.
        return response()->view('cutflow.offline', [], 503);
    }
}
