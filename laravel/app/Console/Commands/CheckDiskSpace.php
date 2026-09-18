<?php

namespace App\Console\Commands;

use App\Models\SystemNotification;
use Illuminate\Console\Command;

/**
 * Raises (or auto-clears) the nav bar "disk space is running low" alert for
 * admins, same alerting pattern as RecordBackupRun::evaluateBackupHealth().
 * Checks the same filesystem StatusController reports on (storage_path()).
 */
class CheckDiskSpace extends Command
{
    protected $signature = 'disk:check';

    protected $description = 'Alert admins when disk usage crosses the warning threshold';

    private const WARN_PERCENT = 70.0;

    public function handle(): int
    {
        $type = 'disk_space_high';
        $storagePath = storage_path();

        $diskFree = @disk_free_space($storagePath);
        $diskTotal = @disk_total_space($storagePath);

        if ($diskFree === false || $diskTotal === false || $diskTotal <= 0) {
            $this->error('Could not read disk usage for '.$storagePath);

            return self::FAILURE;
        }

        $usedPercent = round((1 - $diskFree / $diskTotal) * 100, 1);

        if ($usedPercent >= self::WARN_PERCENT) {
            if (! SystemNotification::active()->ofType($type)->exists()) {
                SystemNotification::create([
                    'type' => $type,
                    'level' => $usedPercent >= 90 ? 'danger' : 'warning',
                    'title' => 'Disk space running low',
                    'message' => sprintf(
                        'Disk usage is at %s%% (%s free of %s). Free up space or expand storage before it fills up.',
                        $usedPercent,
                        $this->formatBytes($diskFree),
                        $this->formatBytes($diskTotal)
                    ),
                    'data' => [
                        'used_percent' => $usedPercent,
                        'disk_free' => $diskFree,
                        'disk_total' => $diskTotal,
                    ],
                ]);
            }
        } else {
            // Usage dropped back below the threshold — auto-resolve any
            // standing alert so nobody has to remember to dismiss it.
            SystemNotification::active()->ofType($type)->update([
                'dismissed_at' => now(),
            ]);
        }

        $this->info("Disk usage: {$usedPercent}%");

        return self::SUCCESS;
    }

    private function formatBytes(float $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));

        return round($bytes / (1024 ** $i), 2).' '.$units[$i];
    }
}
