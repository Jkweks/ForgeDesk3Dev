<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\FdJobStep;
use App\Models\FdWorkOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderPeReviewTest extends TestCase
{
    use RefreshDatabase;

    private function wo(string $jobNumber): FdWorkOrder
    {
        $job = BusinessJob::create(['job_number' => $jobNumber, 'job_name' => 'J '.$jobNumber, 'status' => 'active']);

        return FdWorkOrder::create(['business_job_id' => $job->id, 'release_number' => 1]);
    }

    private function pe(FdWorkOrder $wo): FdJobStep
    {
        return $wo->steps()->where('name', FdJobStep::PE_REVIEW)->firstOrFail();
    }

    public function test_job_number_rule(): void
    {
        foreach (['1260201', '4250303', ' 1111111 '] as $n) {
            $this->assertTrue((new BusinessJob(['job_number' => $n]))->requiresPeReview(), $n);
        }
        // other first digits, and anything that is a date (even one starting with 1)
        foreach (['3251201', '2', '7102026', '7/23/26', '7-21-26', '10-13-26', '11/3/26', '4/5/26', '', 'ABC1'] as $n) {
            $this->assertFalse((new BusinessJob(['job_number' => $n]))->requiresPeReview(), $n);
        }
    }

    public function test_pe_step_is_required_only_for_job_numbers_starting_with_1_or_4(): void
    {
        foreach (['1260201', '4250303'] as $n) {
            $this->assertSame('pending', $this->pe($this->wo($n))->status, $n);
        }
        foreach (['3251201', '7/23/26', '10-13-26'] as $n) {
            $this->assertSame('not_required', $this->pe($this->wo($n))->status, $n);
        }
    }

    public function test_pending_pe_until_the_review_is_done(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $wo = $this->wo('1260201');
        $this->assertTrue($wo->fresh('steps')->isPendingPe());

        // Every other step done, PE review still open: still pending, labelled Pending PE.
        $wo->steps()->where('name', '!=', FdJobStep::PE_REVIEW)->update(['status' => 'complete']);
        $wo->fresh()->refreshPhase();
        $r = $this->getJson("/api/v1/work-orders/{$wo->id}")->assertOk();
        $this->assertSame('pending', $r->json('status'));
        $this->assertTrue($r->json('pe_pending'));
        $this->assertSame('Pending PE', $r->json('status_label'));

        // Steps can be done in any order: completing PE first, via the API, is not gated.
        $this->patchJson('/api/v1/job-steps/'.$this->pe($wo)->id, ['status' => 'complete'])->assertOk();
        $r = $this->getJson("/api/v1/work-orders/{$wo->id}")->assertOk();
        $this->assertSame('active', $r->json('status'));
        $this->assertFalse($r->json('pe_pending'));
    }

    public function test_other_jobs_are_plain_pending_with_no_pe_wait(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $wo = $this->wo('3251201');
        $r = $this->getJson("/api/v1/work-orders/{$wo->id}")->assertOk();
        $this->assertSame('pending', $r->json('status'));
        $this->assertFalse($r->json('pe_pending'));
        $this->assertSame('Pending', $r->json('status_label'));

        // PE not required, so the other steps alone take it active.
        $wo->steps()->where('status', 'pending')->update(['status' => 'complete']);
        $wo->fresh()->refreshPhase();
        $this->assertSame('active', $wo->fresh()->status);
    }

    public function test_pe_review_can_be_the_first_step_completed(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $wo = $this->wo('4250303');
        $this->patchJson('/api/v1/job-steps/'.$this->pe($wo)->id, ['status' => 'complete'])->assertOk();
        $this->assertSame('complete', $this->pe($wo)->status);
    }

    public function test_complete_all_leaves_the_pm_step_alone(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $wo = $this->wo('1260201');

        $this->patchJson("/api/v1/work-orders/{$wo->id}/steps/complete-all")->assertOk();
        $this->assertSame('pending', $this->pe($wo)->status);
        $this->assertSame(0, $wo->steps()->where('name', '!=', FdJobStep::PE_REVIEW)->where('status', 'pending')->count());
        $this->assertSame('pending', $wo->fresh()->status, 'still waiting on PE');
    }
}
