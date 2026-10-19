<?php

namespace App\Console\Commands;

use App\Mail\BackupAlertMail;
use App\Models\BackupRun;
use App\Models\SystemNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
     * Raises (or auto-clears) the backup alerts, each with a bell notification
     * AND an email to config('backup.alert_email'):
     *
     *  - 'backup_local_failed': the newest run had a local failure (DB dump,
     *    storage archive, cutflow/fab_utils dumps). Fires on the FIRST day.
     *  - 'backup_failing': the offsite copy failed (while local succeeded) on
     *    3 consecutive calendar days.
     *
     * Each is created/emailed once per episode and auto-clears when the latest
     * run no longer has that problem.
     */
    private function evaluateBackupHealth(): void
    {
        $latest = BackupRun::orderByDesc('run_date')->orderByDesc('started_at')->first();
        if (! $latest) {
            return;
        }

        $this->evaluateLocalFailure($latest);
        $this->evaluateRemoteFailure();
    }

    private function evaluateLocalFailure(BackupRun $latest): void
    {
        $type = 'backup_local_failed';

        if ($this->localFailed($latest)) {
            if (! SystemNotification::active()->ofType($type)->exists()) {
                $title = 'Local backup failed';
                $message = 'The local backup on '.$latest->run_date->toDateString()
                    .' failed ('.$this->failedComponentNames($latest, false).'). Check backup.sh on the production host.';

                SystemNotification::create([
                    'type' => $type,
                    'level' => 'danger',
                    'title' => $title,
                    'message' => $message,
                    'data' => ['date' => $latest->run_date->toDateString(), 'status' => $latest->status],
                ]);
                $this->sendAlertEmail($title, $message, $latest->components ?? []);
            }
        } else {
            SystemNotification::active()->ofType($type)->update(['dismissed_at' => now()]);
        }
    }

    private function evaluateRemoteFailure(): void
    {
        $type = 'backup_failing';

        // Per calendar day (most recent first): did the offsite copy fail
        // while local succeeded? A small buffer (20 rows) covers multiple runs
        // on the same day.
        $byDate = [];
        foreach (BackupRun::orderByDesc('run_date')->orderByDesc('started_at')->limit(20)->get() as $run) {
            $date = $run->run_date->toDateString();
            $byDate[$date] ??= ['local' => false, 'remote' => false, 'run' => $run];
            $byDate[$date]['local'] = $byDate[$date]['local'] || $this->localFailed($run);
            $byDate[$date]['remote'] = $byDate[$date]['remote'] || $this->remoteFailed($run);
        }

        $dates = array_keys($byDate);
        rsort($dates); // "YYYY-MM-DD" strings sort correctly lexicographically
        $latestThree = array_slice($dates, 0, 3);

        $consecutive = count($latestThree) === 3
            && (int) \Carbon\Carbon::parse($latestThree[1])->diffInDays(\Carbon\Carbon::parse($latestThree[0])) === 1
            && (int) \Carbon\Carbon::parse($latestThree[2])->diffInDays(\Carbon\Carbon::parse($latestThree[1])) === 1;

        $allRemoteOnly = $consecutive && collect($latestThree)->every(fn ($d) => $byDate[$d]['remote'] && ! $byDate[$d]['local']);

        if ($allRemoteOnly) {
            if (! SystemNotification::active()->ofType($type)->exists()) {
                $orderedDates = array_reverse($latestThree);
                $title = 'Offsite backups are failing';
                $message = 'The offsite backup copy has failed for 3 consecutive days ('
                    .$orderedDates[0].' → '.$orderedDates[2].
                    ') while local backups succeeded. Check the remote host / backup.sh on the production host.';

                SystemNotification::create([
                    'type' => $type,
                    'level' => 'danger',
                    'title' => $title,
                    'message' => $message,
                    'data' => ['dates' => $orderedDates],
                ]);
                $this->sendAlertEmail($title, $message, $byDate[$latestThree[0]]['run']->components ?? []);
            }
        } elseif (isset($dates[0]) && ! $byDate[$dates[0]]['remote']) {
            // Most recent day's offsite copy worked — auto-resolve any standing alert.
            SystemNotification::active()->ofType($type)->update(['dismissed_at' => now()]);
        }
    }

    /** Anything other than the remote_* components (or a 'failed'/'local_failed' status) counts as local. */
    private function localFailed(BackupRun $run): bool
    {
        return in_array($run->status, ['failed', 'local_failed'], true)
            || $this->failedComponentNames($run, false) !== '';
    }

    private function remoteFailed(BackupRun $run): bool
    {
        return $run->status === 'remote_failed' || $this->failedComponentNames($run, true) !== '';
    }

    /** Comma-separated names of failed components, remote_* ones or the rest. */
    private function failedComponentNames(BackupRun $run, bool $remote): string
    {
        return collect($run->components ?? [])
            ->filter(fn ($c, $name) => ! ($c['success'] ?? true) && str_starts_with($name, 'remote_') === $remote)
            ->keys()
            ->implode(', ');
    }

    private function sendAlertEmail(string $title, string $message, array $components): void
    {
        $to = config('backup.alert_email');
        if (! $to) {
            return;
        }

        try {
            Mail::to($to)->send(new BackupAlertMail($title, $message, $components));
        } catch (\Throwable $e) {
            // Never let a mail problem break recording the run itself.
            Log::warning('Backup alert email failed: '.$e->getMessage());
        }
    }
}
