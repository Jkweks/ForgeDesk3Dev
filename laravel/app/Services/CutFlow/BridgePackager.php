<?php

namespace App\Services\CutFlow;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * Zips the tiger-bridge service for download from the CutFlow settings page.
 *
 * Ships source only: no node_modules, logs or live .env (which holds the bridge token), and the
 * .env.example token is blanked so a deployed bridge can never run with a value that is public in
 * the repo — the service refuses to start without BRIDGE_TOKEN, which is the point.
 */
class BridgePackager
{
    private const EXCLUDE = ['node_modules', 'logs', '.git'];

    public static function sourceDir(): ?string
    {
        foreach ([config('services.tiger_bridge.source_dir'), '/opt/tiger-bridge', base_path('../tiger-bridge')] as $dir) {
            if ($dir && is_file($dir.'/package.json')) {
                return rtrim($dir, '/');
            }
        }

        return null;
    }

    /** @return string|null path to a temp zip the caller must delete, or null if there is no source */
    public static function build(): ?string
    {
        $src = self::sourceDir();
        if ($src === null) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'tbridge');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $rel = substr($file->getPathname(), strlen($src) + 1);
            $parts = explode('/', $rel);
            if (array_intersect($parts, self::EXCLUDE) || $rel === '.env' || str_starts_with($rel, '.env.') && $rel !== '.env.example') {
                continue;
            }

            if ($rel === '.env.example') {
                $zip->addFromString('tiger-bridge/'.$rel, preg_replace('/^BRIDGE_TOKEN=.*$/m', 'BRIDGE_TOKEN=', file_get_contents($file->getPathname())));
            } else {
                $zip->addFile($file->getPathname(), 'tiger-bridge/'.$rel);
            }
        }
        $zip->close();

        return $tmp;
    }
}
