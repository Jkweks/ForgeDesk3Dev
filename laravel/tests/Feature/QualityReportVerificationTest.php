<?php

namespace Tests\Feature;

use App\Models\QualityReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Coverage for the quality report verify/reject workflow: only a
 * pending_review report can transition, quality.verify (admin/manager only —
 * office_staff has quality.view/create but not .verify) gates who can do it,
 * and the transition stamps verified_by/verified_at.
 */
class QualityReportVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_verify_a_pending_report(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $report = QualityReport::create(['status' => 'pending_review']);

        Sanctum::actingAs($manager, ['*']);

        $response = $this->postJson("/api/v1/quality-reports/{$report->id}/verify");

        $response->assertOk();
        $report->refresh();
        $this->assertSame('verified', $report->status);
        $this->assertSame($manager->id, $report->verified_by);
        $this->assertNotNull($report->verified_at);
    }

    public function test_verifying_an_already_verified_report_is_rejected(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $report = QualityReport::create([
            'status' => 'verified',
            'verified_by' => $manager->id,
            'verified_at' => now(),
        ]);

        Sanctum::actingAs($manager, ['*']);

        $response = $this->postJson("/api/v1/quality-reports/{$report->id}/verify");

        $response->assertStatus(422);
    }

    public function test_office_staff_cannot_verify_a_report(): void
    {
        $officeStaff = User::factory()->create(['role' => 'office_staff', 'is_active' => true]);
        $report = QualityReport::create(['status' => 'pending_review']);

        Sanctum::actingAs($officeStaff, ['*']);

        $response = $this->postJson("/api/v1/quality-reports/{$report->id}/verify");

        $response->assertForbidden();
        $this->assertSame('pending_review', $report->fresh()->status);
    }

    public function test_pending_report_can_be_rejected_with_a_reason(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $report = QualityReport::create(['status' => 'pending_review']);

        Sanctum::actingAs($manager, ['*']);

        $response = $this->postJson("/api/v1/quality-reports/{$report->id}/reject", [
            'reason' => 'Duplicate of another report',
        ]);

        $response->assertOk();
        $report->refresh();
        $this->assertSame('rejected', $report->status);
        $this->assertSame('Duplicate of another report', $report->rejected_reason);
        $this->assertSame($manager->id, $report->verified_by);
    }

    public function test_a_verified_report_cannot_be_rejected(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $report = QualityReport::create([
            'status' => 'verified',
            'verified_by' => $manager->id,
            'verified_at' => now(),
        ]);

        Sanctum::actingAs($manager, ['*']);

        $response = $this->postJson("/api/v1/quality-reports/{$report->id}/reject");

        $response->assertStatus(422);
    }

    public function test_verified_report_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $report = QualityReport::create([
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->deleteJson("/api/v1/quality-reports/{$report->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('quality_reports', ['id' => $report->id]);
    }
}
