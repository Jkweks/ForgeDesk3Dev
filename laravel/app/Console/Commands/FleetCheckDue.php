<?php

namespace App\Console\Commands;

use App\Services\Fleet\FleetService;
use Illuminate\Console\Command;

class FleetCheckDue extends Command
{
    protected $signature = 'fleet:check-due';

    protected $description = 'Raise in-app alerts for overdue maintenance and expiring/expired compliance and documents';

    public function handle(FleetService $fleet): int
    {
        $thresholds = collect(config('fleet.alert_thresholds'))->sort()->values();
        $u = $fleet->upcoming((int) $thresholds->max());
        $raised = 0;

        foreach ($u['plans'] as $p) {
            $overdue = $p['state'] === 'overdue';
            $fleet->notify('fleet_maintenance_due', $overdue ? 'danger' : 'warning',
                ($overdue ? 'Maintenance overdue: ' : 'Maintenance due soon: ')."{$p['asset']['asset_number']} {$p['title']} [{$p['id']}:".($p['next_due_at'] ?? $p['next_due_meter']).']',
                "{$p['asset']['name']} — {$p['title']}".($p['next_due_at'] ? " (due {$p['next_due_at']})" : '').'.',
                ['asset_id' => $p['asset']['id'], 'plan_id' => $p['id']]);
            $raised++;
        }

        foreach (['compliance' => 'Compliance', 'documents' => 'Document'] as $key => $noun) {
            foreach ($u[$key] as $c) {
                $days = (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($c['expires_on']), false);
                // Alert once per threshold crossed (90/60/30/7) and once when expired.
                $bucket = $days < 0 ? 'expired' : ($thresholds->first(fn ($t) => $days <= $t) ?? null);
                if ($bucket === null) {
                    continue;
                }
                $name = $c['kind'] ?? $c['label'];
                $label = ucfirst(str_replace('_', ' ', $name));
                $fleet->notify('fleet_expiry', $days < 0 || $days <= 7 ? 'danger' : 'warning',
                    "{$noun} ".($bucket === 'expired' ? 'expired' : "expires within {$bucket}d").": {$c['asset']['asset_number']} {$label} [{$c['id']}]",
                    "{$c['asset']['name']} — {$label} ".($days < 0 ? 'expired '.abs($days).' day(s) ago' : "expires {$c['expires_on']} ({$days} days)").'.',
                    ['asset_id' => $c['asset']['id'], 'kind' => $key, 'record_id' => $c['id']]);
                $raised++;
            }
        }

        $this->info("Checked fleet due items ({$raised} active).");

        return self::SUCCESS;
    }
}
