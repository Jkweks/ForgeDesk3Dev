<?php

namespace App\Console\Commands;

use App\Models\WorkOrderRequest;
use App\Services\Pm\WorkOrderElevationSeeder;
use Illuminate\Console\Command;

/** Back-fill: seed the Fabrication work order of an ALREADY ACCEPTED request with the elevations the request released. */
class SeedRequestElevations extends Command
{
    protected $signature = 'pm:seed-request-elevations {request : The work order request id} {--dry-run : Say what would be created and change nothing}';

    protected $description = "Seed an accepted work order request's elevations onto its Fabrication work order (safe to repeat)";

    public function handle(WorkOrderElevationSeeder $seeder): int
    {
        $r = WorkOrderRequest::with(['elevations', 'workOrder'])->find($this->argument('request'));
        if (! $r) {
            $this->error('No such work order request.');

            return self::FAILURE;
        }
        if ($r->status !== 'approved' || ! $r->workOrder) {
            $this->error("Request {$r->id} is {$r->status}; only an accepted request with a work order can be seeded.");

            return self::FAILURE;
        }
        $todo = $r->elevations->filter(fn ($l) => ! $l->fd_wo_elevation_id || ! \App\Models\FdWoElevation::whereKey($l->fd_wo_elevation_id)->exists());
        $this->line("Request {$r->id} → work order {$r->workOrder->id} (release {$r->workOrder->release_number}): {$todo->count()} of {$r->elevations->count()} elevations still to seed.");
        if ($this->option('dry-run')) {
            $todo->each(function ($l) use ($seeder) {
                $type = $seeder->typeFor($l->system);
                $tier = $seeder->tierFor($type, $l->system);
                $this->line(' - '.$l->summary().' → type '.($type ? \App\Models\FdElevationType::find($type)->name : 'none').', tier '.($tier ? \App\Models\FdStageTemplateSet::find($tier)->name : 'default'));
            });

            return self::SUCCESS;
        }
        $made = $seeder->seed($r, $r->workOrder);
        $this->info("Created {$made} elevation".($made === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
