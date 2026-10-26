<?php

namespace App\Console\Commands;

use App\Services\Fleet\FieldSync;
use Illuminate\Console\Command;
use Throwable;

class FleetSyncIn extends Command
{
    protected $signature = 'fleet:sync-in';

    protected $description = 'Pull inspections, meter readings and defect reports from the field app inbox';

    public function handle(FieldSync $sync): int
    {
        if (! $sync->enabled()) {
            $this->line('Field sync disabled (FIELDSYNC_ENABLED=false).');

            return self::SUCCESS;
        }
        try {
            $r = $sync->ingest();
            $this->info(sprintf('Ingested %d, needs review %d, rejected %d, skipped %d.', $r['ingested'], $r['needs_review'], $r['rejected'], $r['skipped']));

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error('Field sync-in failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
