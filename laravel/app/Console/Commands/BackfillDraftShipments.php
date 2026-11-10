<?php

namespace App\Console\Commands;

use App\Models\FdWorkOrder;
use App\Services\Shipping\WorkOrderShipmentService;
use Illuminate\Console\Command;

class BackfillDraftShipments extends Command
{
    protected $signature = 'shipments:backfill-drafts {--dry-run : Only report what would be created}';

    protected $description = 'Create a draft shipment (with all elevations) for open work orders that have none; exempt jobs are skipped';

    public function handle(WorkOrderShipmentService $svc): int
    {
        $made = 0;
        FdWorkOrder::with(['businessJob', 'elevations.elevationType'])->where('archived', false)->whereIn('status', FdWorkOrder::OPEN_STATUSES)
            ->doesntHave('shipments')->orderBy('id')->each(function (FdWorkOrder $wo) use ($svc, &$made) {
                if (WorkOrderShipmentService::jobIsExempt($wo->businessJob) || ! $wo->businessJob) {
                    return;
                }
                $made++;
                if ($this->option('dry-run')) {
                    $this->line("would create for WO {$wo->id} ({$wo->businessJob->job_number})");

                    return;
                }
                $draft = $svc->createDraftFor($wo);
                foreach ($wo->elevations as $e) {
                    $draft?->elevations()->syncWithoutDetaching([$e->id]);
                    $draft && $svc->ensureDefaultCategory($draft, $e);
                }
            });
        $this->info(($this->option('dry-run') ? 'Would create ' : 'Created ').$made.' draft shipment(s).');

        return self::SUCCESS;
    }
}
