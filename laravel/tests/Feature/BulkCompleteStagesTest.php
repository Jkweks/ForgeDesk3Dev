<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\FdUser;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Models\FdWoStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Bulk-complete controls on the work-order panel:
 *  - PATCH /work-orders/{id}/stages/bulk-complete  — one stage, every elevation
 *  - PATCH /elevations/{id}/complete-all-stages     — every stage on one line
 *
 * A fabricator credit (fab_user_id) sticks only for manager/admin app users.
 */
class BulkCompleteStagesTest extends TestCase
{
    use RefreshDatabase;

    private BusinessJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->job = BusinessJob::create(['job_number' => 'J1', 'job_name' => 'J', 'status' => 'active']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
    }

    private function wo(array $attr = []): FdWorkOrder
    {
        static $rel = 0;

        return FdWorkOrder::create(array_merge([
            'business_job_id' => $this->job->id,
            'release_number' => ++$rel,
        ], $attr));
    }

    private function elevation(FdWorkOrder $wo): FdWoElevation
    {
        return FdWoElevation::create([
            'work_order_id' => $wo->id,
            'elevation_tag' => 'E'.$wo->id.'-'.uniqid(),
        ]);
    }

    private function stage(FdWoElevation $e, string $name, int $order, array $attr = []): FdWoStage
    {
        return FdWoStage::create(array_merge([
            'elevation_id' => $e->id,
            'name' => $name,
            'sort_order' => $order,
            'blocks_next' => true,
            'status' => 'pending',
        ], $attr));
    }

    public function test_bulk_complete_stage_closes_that_stage_on_every_elevation(): void
    {
        $fab = FdUser::create(['name' => 'Fabby', 'initials' => 'FB', 'role' => 'worker', 'active' => true]);
        $wo = $this->wo();

        $e1 = $this->elevation($wo);
        $this->stage($e1, 'Cut', 1, ['status' => 'complete']);
        $s1 = $this->stage($e1, 'Weld', 2);

        $e2 = $this->elevation($wo);
        $this->stage($e2, 'Cut', 1, ['status' => 'complete']);
        $s2 = $this->stage($e2, 'Weld', 2, ['status' => 'in_progress']);

        $other = $this->stage($e1, 'Paint', 3);

        $this->patchJson("/api/v1/work-orders/{$wo->id}/stages/bulk-complete", [
            'stage_name' => 'weld',
            'fab_user_id' => $fab->id,
        ])->assertOk()->assertJson(['updated' => 2]);

        $this->assertSame('complete', $s1->fresh()->status);
        $this->assertSame('complete', $s2->fresh()->status);
        $this->assertSame($fab->id, $s1->fresh()->completed_by_id);
        $this->assertNotNull($s1->fresh()->completed_at);
        // A differently-named stage is untouched.
        $this->assertSame('pending', $other->fresh()->status);
    }

    public function test_bulk_complete_stage_is_gate_blocked_without_override(): void
    {
        $wo = $this->wo();
        $e = $this->elevation($wo);
        $this->stage($e, 'Cut', 1);          // still pending — blocks Weld
        $weld = $this->stage($e, 'Weld', 2);

        $this->patchJson("/api/v1/work-orders/{$wo->id}/stages/bulk-complete", [
            'stage_name' => 'Weld',
        ])->assertStatus(422)->assertJson(['code' => 'stage_gated']);

        $this->assertSame('pending', $weld->fresh()->status);

        $this->patchJson("/api/v1/work-orders/{$wo->id}/stages/bulk-complete", [
            'stage_name' => 'Weld',
            'override' => true,
        ])->assertOk()->assertJson(['updated' => 1]);

        $this->assertSame('complete', $weld->fresh()->status);
    }

    public function test_complete_all_stages_closes_the_elevation(): void
    {
        $fab = FdUser::create(['name' => 'Fabby', 'initials' => 'FB', 'role' => 'worker', 'active' => true]);
        $wo = $this->wo();
        $e = $this->elevation($wo);
        $this->stage($e, 'Cut', 1);
        $this->stage($e, 'Weld', 2, ['status' => 'in_progress']);
        $this->stage($e, 'QC', 3, ['status' => 'not_required']);

        $this->patchJson("/api/v1/elevations/{$e->id}/complete-all-stages", [
            'fab_user_id' => $fab->id,
        ])->assertOk();

        $e->refresh();
        $this->assertNotNull($e->date_completed);
        $this->assertSame($fab->id, $e->completed_by_id);
        $this->assertTrue($e->stages->every(fn ($s) => in_array($s->status, ['complete', 'not_required'], true)));
        $this->assertSame($fab->id, $e->stages->firstWhere('name', 'Cut')->completed_by_id);
    }

    public function test_non_manager_cannot_credit_a_fabricator(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'fabricator', 'is_active' => true]), ['*']);
        $fab = FdUser::create(['name' => 'Fabby', 'initials' => 'FB', 'role' => 'worker', 'active' => true]);

        $wo = $this->wo();
        $e = $this->elevation($wo);
        $cut = $this->stage($e, 'Cut', 1);

        $this->patchJson("/api/v1/elevations/{$e->id}/complete-all-stages", [
            'fab_user_id' => $fab->id,
        ])->assertOk();

        $this->assertSame('complete', $cut->fresh()->status);
        $this->assertNull($cut->fresh()->completed_by_id);
        $this->assertNull($e->fresh()->completed_by_id);
    }
}
