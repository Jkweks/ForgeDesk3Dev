<?php

namespace App\Console\Commands;

use App\Models\BusinessJob;
use App\Models\JobReservation;
use App\Services\Configurator\ConfigurationReservationBridge;
use Illuminate\Console\Command;

/**
 * One-off: recompute every active configurator reservation from its job's configurations so they pick
 * up a change in the reservation maths (e.g. extrusions moving from whole sticks to 1/10 of a stick).
 * Dry run unless --apply; prints each item whose committed quantity would change.
 */
class ResyncConfiguratorReservations extends Command
{
    protected $signature = 'configurator:resync-reservations {--apply : Write the changes (default is a dry run)}';

    protected $description = 'Recompute active configurator job reservations with the current reservation maths';

    public function handle(ConfigurationReservationBridge $bridge): int
    {
        $apply = (bool) $this->option('apply');
        $changed = 0;

        $reservations = JobReservation::where('source', 'configurator')
            ->whereIn('status', ['active', 'in_progress', 'on_hold'])
            ->get();

        foreach ($reservations as $reservation) {
            $job = BusinessJob::find($reservation->business_job_id);
            if (! $job) {
                continue;
            }

            if (! $apply) {
                $configs = \App\Models\DoorFrameConfiguration::with(['frameConfig.parts.product', 'doorConfigs.parts.product', 'hardwareParts.product'])
                    ->where('business_job_id', $job->id)->whereIn('status', ConfigurationReservationBridge::COUNTED_STATUSES)->get();
                $warnings = [];
                $desired = $bridge->desiredQuantities($configs, $warnings);
                $current = $reservation->items()->get()->keyBy('product_id');
                foreach ($desired as $pid => $qty) {
                    $was = (float) ($current->get($pid)?->committed_qty ?? 0);
                    if (abs($was - $qty) > 0.0001) {
                        $changed++;
                        $this->line("job {$job->job_number} product #{$pid}: {$was} -> {$qty}");
                    }
                }

                continue;
            }

            $before = $reservation->items()->get()->pluck('committed_qty', 'product_id')->map(fn ($q) => (float) $q);
            $warnings = [];
            $bridge->syncJob($job, null, $warnings);
            $after = $reservation->items()->get()->pluck('committed_qty', 'product_id')->map(fn ($q) => (float) $q);

            foreach ($after as $pid => $qty) {
                if (abs(($before[$pid] ?? 0) - $qty) > 0.0001) {
                    $changed++;
                    $this->line("job {$job->job_number} product #{$pid}: ".($before[$pid] ?? 0)." -> {$qty}");
                }
            }
            foreach ($warnings as $w) {
                $this->warn("job {$job->job_number}: {$w}");
            }
        }

        $this->info(($apply ? 'Updated' : 'Would update')." {$changed} reservation item(s) across {$reservations->count()} reservation(s).");

        return self::SUCCESS;
    }
}
