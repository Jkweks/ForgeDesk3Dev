<?php

namespace App\Console\Commands;

use App\Services\Pm\PmFileIngestService;
use Illuminate\Console\Command;

/**
 * Safety net for the F: watcher: walks the configured roots (read-only) and feeds every file through the
 * same ingest the watcher uses. Cheap when nothing changed (path + size + mtime match, no hashing).
 */
class PmScanFdrive extends Command
{
    protected $signature = 'pm:scan-fdrive {--root=* : Folder(s) to scan instead of the configured roots} {--limit=5000 : Stop after this many files}';

    protected $description = 'Reconcile the F: share with the Project Management file registry';

    public function handle(PmFileIngestService $ingest): int
    {
        $roots = $this->option('root') ?: (array) config('services.fdrive_ingest.roots');
        if (! $roots) {
            $this->warn('No roots configured (FDRIVE_SCAN_ROOTS) and none given with --root.');

            return self::SUCCESS;
        }

        $counts = [];
        $seen = 0;
        foreach ($roots as $root) {
            if (! is_dir($root)) {
                $this->warn("Skipping {$root}: not a readable folder.");

                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (! $f->isFile() || ++$seen > (int) $this->option('limit')) {
                    continue;
                }
                $r = $ingest->handle(['path' => $f->getPathname(), 'size' => $f->getSize(), 'mtime' => date('c', $f->getMTime())]);
                $counts[$r['action']] = ($counts[$r['action']] ?? 0) + 1;
            }
        }
        ksort($counts);
        $this->info("Scanned {$seen} files: ".json_encode($counts));

        return self::SUCCESS;
    }
}
