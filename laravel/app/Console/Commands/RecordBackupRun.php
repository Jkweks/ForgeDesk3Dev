<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Models\SystemNotification;
use Illuminate\Console\Command;

/**
 * Records the result of one backup.sh run so the /status page can show
 * backup health. backup.sh calls this over `docker exec -i` with a JSON
 * payload on stdin — it already has container access to APP_CONTAINER, so
 * this avoids needing a separate HTTP endpoint/token.
 *
 * Expected payload shape:
 * {
 *   "run_date": "2026-09-14",
 *   "started_at": "2026-09-14T03:00:01+00:00",
 *   "finished_at": "2026-09-14T03:02:14+00:00",
 *   "status": "success|local_failed|remote_failed|failed",
 *   "components": {"db": {"success": true, "message": "..."}, ...},
 *   "error_message": null
 * }
 */
class RecordBackupRun extends Command
{
    protected $signature = 'backup:record';

    protected $description = 'Record the result of an external backup.sh run (payload read as JSON from stdin)';

    public function handle(): int
    {
        $payload = json_decode(stream_get_contents(STDIN) ?: '', true);

        if (! is_array($payload) || empty($payload['status']) || ! in_array($payload['status'], BackupRun::STATUSES, true)) {
            $this->error('Invalid backup run payload (missing or unrecognized "status").');

            return self::FAILURE;
        }

        BackupRun::create([
            'run_date' => $payload['run_date'] ?? now()->toDateString(),
            'started_at' => $payload['started_at'] ?? now(),
            'finished_at' => $payload['finished_at'] ?? now(),
            'status' => $payload['status'],
            'components' => $payload['components'] ?? [],
            'error_message' => $payload['error_message'] ?? null,
        ]);

        $this->evaluateBackupHealth();

        $this->info('Backup run recorded: '.$payload['status']);

        return self::SUCCESS;
    }

    /**
     * Raises (or auto-clears) the "backups are failing" nav bar alert.
     * Fires when the last 3 *consecutive* calendar days each landed on
     * 'failed' (red) or 'remote_failed' (orange) — local_failed (yellow)
     * alone never triggers it. Clears automatically the next time a day's
     * worst run is 'success', so nobody has to remember to dismiss it once
     * the underlying problem is fixed.
     */
    private function evaluateBackupHealth(): void
    {
        $type = 'backup_failing';
        $alertStatuses = ['failed', 'remote_failed'];
        $severity = ['failed' => 3, 'remote_failed' => 2, 'local_failed' => 1, 'success' => 0];

        // Worst status per calendar day, most recent days first. A small
        // buffer (20 rows) comfortably covers multiple runs on the same day.
        $byDate = [];
        foreach (BackupRun::orderByDesc('run_date')->orderByDesc('started_at')->limit(20)->get() as $run) {
            $date = $run->run_date->toDateString();
            if (! isset($byDate[$date]) || $severity[$run->status] > $severity[$byDate[$date]]) {
                $byDate[$date] = $run->status;
            }
        }

        $dates = array_keys($byDate);
        rsort($dates); // "YYYY-MM-DD" strings sort correctly lexicographically
        $latestThree = array_slice($dates, 0, 3);

        $consecutive = count($latestThree) === 3
            && (int) \Carbon\Carbon::parse($latestThree[1])->diffInDays(\Carbon\Carbon::parse($latestThree[0])) === 1
            && (int) \Carbon\Carbon::parse($latestThree[2])->diffInDays(\Carbon\Carbon::parse($latestThree[1])) === 1;

        $allAlerting = $consecutive && collect($latestThree)->every(fn ($d) => in_array($byDate[$d], $alertStatuses, true));

        if ($allAlerting) {
            if (! SystemNotification::active()->ofType($type)->exists()) {
                $orderedDates = array_reverse($latestThree);
                SystemNotification::create([
                    'type' => $type,
                    'level' => 'danger',
                    'title' => 'Backups are failing',
                    'message' => 'The backup process has failed or lost its offsite copy for 3 consecutive days ('
                        .$orderedDates[0].' → '.$orderedDates[2].
                        '). Check backup.sh on the production host.',
                    'data' => [
                        'dates' => $orderedDates,
                        'statuses' => array_map(fn ($d) => $byDate[$d], $orderedDates),
                    ],
                ]);
            }
        } elseif (($byDate[$dates[0] ?? ''] ?? null) === 'success') {
            // Most recent day succeeded — auto-resolve any standing alert.
            SystemNotification::active()->ofType($type)->update([
                'dismissed_at' => now(),
            ]);
        }
    }
}
