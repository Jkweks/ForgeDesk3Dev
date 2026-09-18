<?php

namespace Tests\Feature;

use App\Mail\WorkOrderCompletedMail;
use App\Models\BusinessJob;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Models\FdWoStatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Work-order lifecycle status:
 *  - PATCH /work-orders/{id}/status            — active | on_hold | complete
 *  - POST  /work-orders/{id}/completion-email  — notify PM + admins (manager/admin only)
 */
class WorkOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    private BusinessJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->job = BusinessJob::create(['job_number' => 'J1', 'job_name' => 'Job One', 'status' => 'active']);
    }

    private function actingAdmin(): User
    {
        $u = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($u, ['*']);

        return $u;
    }

    private function wo(): FdWorkOrder
    {
        static $rel = 0;

        return FdWorkOrder::create([
            'business_job_id' => $this->job->id,
            'release_number' => ++$rel,
        ]);
    }

    /** A WO whose elevations and WO-level steps are all complete. */
    private function readyWo(): FdWorkOrder
    {
        $wo = $this->wo();
        FdWoElevation::create([
            'work_order_id' => $wo->id,
            'elevation_tag' => 'E1',
            'date_completed' => now()->toDateString(),
        ]);
        $wo->steps()->update(['status' => 'complete']);

        return $wo->fresh(['elevations.stages', 'steps']);
    }

    public function test_on_hold_requires_a_note(): void
    {
        $this->actingAdmin();
        $wo = $this->wo();

        $this->patchJson("/api/v1/work-orders/{$wo->id}/status", ['status' => 'on_hold'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'note_required');

        $this->assertSame('active', $wo->fresh()->status);
    }

    public function test_on_hold_with_a_note_is_recorded(): void
    {
        $this->actingAdmin();
        $wo = $this->wo();

        $this->patchJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => 'on_hold',
            'note' => 'Waiting on glass delivery',
        ])->assertOk()->assertJsonPath('work_order.status', 'on_hold');

        $this->assertSame('on_hold', $wo->fresh()->status);
        $log = FdWoStatusLog::where('work_order_id', $wo->id)->latest('id')->first();
        $this->assertSame('active', $log->from_status);
        $this->assertSame('on_hold', $log->to_status);
        $this->assertSame('Waiting on glass delivery', $log->note);
    }

    public function test_complete_is_rejected_while_work_is_open(): void
    {
        $this->actingAdmin();
        $wo = $this->wo(); // boot() seeds 4 pending steps, no elevations

        $this->patchJson("/api/v1/work-orders/{$wo->id}/status", ['status' => 'complete'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'not_ready')
            ->assertJsonStructure(['blockers']);

        $this->assertSame('active', $wo->fresh()->status);
    }

    public function test_complete_succeeds_when_everything_is_done(): void
    {
        $admin = $this->actingAdmin();
        $wo = $this->readyWo();

        $this->patchJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => 'complete',
            'note' => 'Shipped',
        ])->assertOk()->assertJsonPath('work_order.status', 'complete');

        $wo->refresh();
        $this->assertSame('complete', $wo->status);
        $this->assertNotNull($wo->completed_at);
        $this->assertSame($admin->id, $wo->completed_by_user_id);
    }

    public function test_completion_email_is_sent_to_pm_and_admins_for_a_manager(): void
    {
        Mail::fake();

        $pm = User::factory()->create(['role' => 'manager', 'is_active' => true, 'email' => 'pm@example.com']);
        $super = User::factory()->create(['role' => 'viewer', 'is_active' => true, 'email' => 'super@example.com']);
        User::factory()->create(['role' => 'admin', 'is_active' => true, 'email' => 'admin@example.com']);
        $this->job->update(['project_manager_id' => $pm->id, 'superintendent_id' => $super->id]);

        Sanctum::actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]), ['*']);
        $wo = $this->readyWo();
        $wo->update(['status' => 'complete', 'completed_at' => now()]);

        $this->postJson("/api/v1/work-orders/{$wo->id}/completion-email", ['note' => 'All good'])
            ->assertOk()
            ->assertJsonPath('sent', true);

        Mail::assertQueued(WorkOrderCompletedMail::class, function ($mail) use ($wo) {
            $subject = $mail->envelope()->subject;

            return $mail->hasTo('pm@example.com')
                && $mail->hasTo('super@example.com')
                && $mail->hasTo('admin@example.com')
                && $subject === "Work Order Complete: Job One - WO{$wo->release_number} (see notes)";
        });
        $this->assertNotNull($wo->fresh()->completion_email_sent_at);
    }

    public function test_completion_email_subject_omits_notes_tag_when_no_note(): void
    {
        $this->actingAdmin();
        $wo = $this->readyWo();
        $wo->update(['status' => 'complete', 'completed_at' => now()]);

        $subject = (new WorkOrderCompletedMail($wo->fresh('businessJob')))->envelope()->subject;

        $this->assertSame("Work Order Complete: Job One - WO{$wo->release_number}", $subject);
    }

    public function test_completion_email_is_forbidden_for_non_managers(): void
    {
        Mail::fake();
        Sanctum::actingAs(User::factory()->create(['role' => 'fabricator', 'is_active' => true]), ['*']);

        $wo = $this->readyWo();
        $wo->update(['status' => 'complete', 'completed_at' => now()]);

        $this->postJson("/api/v1/work-orders/{$wo->id}/completion-email")
            ->assertStatus(403);

        Mail::assertNothingQueued();
    }
}
