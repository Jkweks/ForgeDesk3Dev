<?php

namespace Tests\Feature;

use App\Mail\BackupAlertMail;
use App\Models\BackupRun;
use App\Models\SystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BackupAlertTest extends TestCase
{
    use RefreshDatabase;

    private function record(string $date, string $status, array $components = []): void
    {
        $payload = json_encode([
            'run_date' => $date,
            'started_at' => $date.'T03:00:00+00:00',
            'finished_at' => $date.'T03:02:00+00:00',
            'status' => $status,
            'components' => $components,
        ]);
        BackupRun::create(json_decode($payload, true));
        // Re-run the evaluator the same way backup:record does.
        $cmd = new \App\Console\Commands\RecordBackupRun;
        $m = new \ReflectionMethod($cmd, 'evaluateBackupHealth');
        $m->setAccessible(true);
        $m->invoke($cmd);
    }

    public function test_local_failure_alerts_and_emails_on_first_day_once(): void
    {
        Mail::fake();
        $this->record('2026-10-10', 'failed', ['forgedesk_db' => ['success' => false, 'message' => 'x']]);

        $this->assertSame(1, SystemNotification::active()->ofType('backup_local_failed')->count());
        Mail::assertSent(BackupAlertMail::class, fn ($m) => $m->hasTo('jonk@vosglass.com'));

        $this->record('2026-10-11', 'local_failed', ['storage' => ['success' => false, 'message' => 'x']]);
        Mail::assertSent(BackupAlertMail::class, 1);

        $this->record('2026-10-12', 'success');
        $this->assertSame(0, SystemNotification::active()->ofType('backup_local_failed')->count());
    }

    public function test_remote_failure_alerts_only_on_third_consecutive_day(): void
    {
        Mail::fake();
        $remote = ['forgedesk_db' => ['success' => true], 'remote_copy' => ['success' => false, 'message' => 'x']];

        $this->record('2026-10-10', 'remote_failed', $remote);
        $this->record('2026-10-11', 'remote_failed', $remote);
        Mail::assertNothingSent();
        $this->assertSame(0, SystemNotification::count());

        $this->record('2026-10-12', 'remote_failed', $remote);
        Mail::assertSent(BackupAlertMail::class, 1);
        $this->assertSame(1, SystemNotification::active()->ofType('backup_failing')->count());
        $this->assertSame(0, SystemNotification::active()->ofType('backup_local_failed')->count());

        $this->record('2026-10-13', 'success');
        $this->assertSame(0, SystemNotification::active()->ofType('backup_failing')->count());
    }
}
