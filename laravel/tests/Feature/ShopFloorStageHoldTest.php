<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\FdStageLog;
use App\Models\FdUser;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Models\FdWoStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kiosk press-and-hold / right-click "put this stage on hold" action:
 * PATCH /api/v1/shop/stages/{id}/status  (unauthenticated tablet route).
 */
class ShopFloorStageHoldTest extends TestCase
{
    use RefreshDatabase;

    private function stage(array $attr = []): FdWoStage
    {
        $job = BusinessJob::create(['job_number' => 'J1', 'job_name' => 'J', 'status' => 'active']);
        $wo = FdWorkOrder::create(['business_job_id' => $job->id, 'release_number' => 1]);
        $e = FdWoElevation::create(['work_order_id' => $wo->id, 'elevation_tag' => 'A1']);

        return FdWoStage::create(array_merge([
            'elevation_id' => $e->id,
            'name' => 'Weld',
            'sort_order' => 1,
            'blocks_next' => true,
            'status' => 'pending',
        ], $attr));
    }

    public function test_shop_can_put_a_stage_on_hold(): void
    {
        $fab = FdUser::create(['name' => 'Fabby', 'initials' => 'FB', 'role' => 'worker', 'active' => true]);
        $s = $this->stage(['status' => 'in_progress', 'started_at' => now()]);

        $this->patchJson("/api/v1/shop/stages/{$s->id}/status", [
            'status' => 'on_hold',
            'fab_user_id' => $fab->id,
        ])->assertOk()->assertJson(['status' => 'on_hold']);

        $s->refresh();
        $this->assertSame('on_hold', $s->status);
        $this->assertNull($s->started_at);
        $this->assertSame(1, FdStageLog::where('stage_id', $s->id)->count());
    }

    public function test_shop_can_take_a_stage_off_hold(): void
    {
        $s = $this->stage(['status' => 'on_hold']);

        $this->patchJson("/api/v1/shop/stages/{$s->id}/status", [
            'status' => 'pending',
        ])->assertOk()->assertJson(['status' => 'pending']);

        $this->assertSame('pending', $s->fresh()->status);
    }

    public function test_shop_stage_status_rejects_non_hold_values(): void
    {
        $s = $this->stage();

        $this->patchJson("/api/v1/shop/stages/{$s->id}/status", [
            'status' => 'complete',
        ])->assertStatus(422);

        $this->assertSame('pending', $s->fresh()->status);
    }
}
