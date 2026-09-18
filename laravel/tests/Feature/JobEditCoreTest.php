<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\JobReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Editing a job's identity fields after creation is gated by jobs.edit-core
 * (manager + admin). A job-number change cascades to linked reservations.
 */
class JobEditCoreTest extends TestCase
{
    use RefreshDatabase;

    private function job(): BusinessJob
    {
        return BusinessJob::create([
            'job_number' => 'J-1000', 'job_name' => 'Original', 'status' => 'active',
            'customer_name' => 'Acme',
        ]);
    }

    public function test_jobs_edit_only_user_can_touch_status_and_notes_but_not_identity(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'fabricator', 'is_active' => true, 'welcome_email_sent_at' => now()]), ['*']);
        $job = $this->job();

        // Status / notes — allowed with plain jobs.edit.
        $this->putJson("/api/v1/business-jobs/{$job->id}", ['status' => 'on_hold', 'notes' => 'paused'])
            ->assertOk();
        $this->assertSame('on_hold', $job->fresh()->status);

        // Re-sending the same identity values alongside is fine (nothing changed).
        $this->putJson("/api/v1/business-jobs/{$job->id}", [
            'job_number' => 'J-1000', 'job_name' => 'Original', 'customer_name' => 'Acme', 'status' => 'active',
        ])->assertOk();

        // Actually changing an identity field — blocked.
        $this->putJson("/api/v1/business-jobs/{$job->id}", ['job_name' => 'Renamed'])
            ->assertStatus(403);
        $this->putJson("/api/v1/business-jobs/{$job->id}", ['job_number' => 'J-9999'])
            ->assertStatus(403);

        $this->assertSame('Original', $job->fresh()->job_name);
    }

    public function test_manager_can_edit_identity_and_job_number_rename_cascades(): void
    {
        $pm = User::factory()->create(['role' => 'manager', 'is_active' => true, 'first_name' => 'Pat', 'last_name' => 'Manager', 'name' => 'Pat Manager']);
        Sanctum::actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true, 'welcome_email_sent_at' => now()]), ['*']);
        $job = $this->job();

        // A reservation linked by id, and a legacy one linked only by the number string.
        $linked = JobReservation::create(['business_job_id' => $job->id, 'job_number' => 'J-1000', 'job_name' => 'x', 'requested_by' => 'a', 'status' => 'active']);
        $legacy = JobReservation::create(['reservation_id' => 1, 'job_number' => 'J-1000', 'job_name' => 'x', 'requested_by' => 'a', 'status' => 'active']);
        $unrelated = JobReservation::create(['reservation_id' => 1, 'job_number' => 'J-2000', 'job_name' => 'y', 'requested_by' => 'a', 'status' => 'active']);

        $res = $this->putJson("/api/v1/business-jobs/{$job->id}", [
            'job_number' => 'J-1000-R2',
            'job_name' => 'Renamed Job',
            'project_manager_id' => $pm->id,
        ])->assertOk();

        $res->assertJsonPath('reservations_renamed', 2);

        $job->refresh();
        $this->assertSame('J-1000-R2', $job->job_number);
        $this->assertSame('Renamed Job', $job->job_name);
        $this->assertSame($pm->id, $job->project_manager_id);
        $this->assertSame('Manager, Pat', $job->project_manager);

        $this->assertSame('J-1000-R2', $linked->fresh()->job_number);
        $this->assertSame('J-1000-R2', $legacy->fresh()->job_number);
        $this->assertSame('J-2000', $unrelated->fresh()->job_number, 'unrelated reservation untouched');
    }

    public function test_admin_can_edit_identity(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true, 'welcome_email_sent_at' => now()]), ['*']);
        $job = $this->job();

        $this->putJson("/api/v1/business-jobs/{$job->id}", ['job_name' => 'Admin Renamed'])->assertOk();
        $this->assertSame('Admin Renamed', $job->fresh()->job_name);
    }

    public function test_manager_role_was_granted_the_new_permission(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $this->assertTrue($manager->hasPermission('jobs.edit-core'));

        $fab = User::factory()->create(['role' => 'fabricator', 'is_active' => true]);
        $this->assertFalse($fab->hasPermission('jobs.edit-core'));
        $this->assertTrue($fab->hasPermission('jobs.edit'));
    }
}
