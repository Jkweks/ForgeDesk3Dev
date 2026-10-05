<?php

namespace App\Console\Commands;

use App\Models\FdWorkOrder;
use App\Services\Configurator\ElevationConfigurationMatcher;
use Illuminate\Console\Command;

/**
 * Backfills a DoorFrameConfiguration for every Door/Frame opening on active
 * work orders that doesn't already have one. Safe to re-run — existing
 * matches are left untouched (see ElevationConfigurationMatcher).
 */
class SyncElevationConfigurations extends Command
{
    protected $signature = 'configurator:sync-elevations {--all : Include archived and completed work orders, not just active ones}';

    protected $description = 'Create a matching DoorFrameConfiguration for every door/frame opening on work orders that lacks one';

    public function handle(ElevationConfigurationMatcher $matcher): int
    {
        $query = FdWorkOrder::query();
        if (! $this->option('all')) {
            $query->where('archived', false)->where('status', 'active');
        }

        $workOrders = $query->get();
        $this->info("Scanning {$workOrders->count()} work order(s)...");

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($workOrders as $wo) {
            $result = $matcher->syncWorkOrder($wo);
            if ($result['created'] > 0) {
                $this->line("WO #{$wo->id} ({$wo->release_token}): created {$result['created']}, already matched {$result['skipped']}.");
            }
            $totalCreated += $result['created'];
            $totalSkipped += $result['skipped'];
        }

        $this->info("Done. Created {$totalCreated} configuration(s); {$totalSkipped} opening(s) already matched.");

        return self::SUCCESS;
    }
}
