<?php

namespace App\Console\Commands;

use App\Services\Fleet\FieldSync;
use Illuminate\Console\Command;
use Throwable;

class FleetSyncOut extends Command
{
    protected $signature = 'fleet:sync-out';

    protected $description = 'Publish the asset + checklist snapshot to the field app database';

    public function handle(FieldSync $sync): int
    {
        if (! $sync->enabled()) {
            $this->line('Field sync disabled (FIELDSYNC_ENABLED=false).');

            return self::SUCCESS;
        }
        try {
            $r = $sync->publish();
            $this->info("Published {$r['assets']} assets, {$r['checklists']} checklists.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            // Field DB unreachable must never take ForgeDesk down: log and retry next run.
            report($e);
            $this->error('Field sync-out failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
