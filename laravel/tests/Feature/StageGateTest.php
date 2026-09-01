<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\FdStageLog;
use App\Models\FdUser;
use App\Models\FdWoElevation;
use App\Models\FdWoStage;
use App\Models\FdWorkOrder;
use App\Models\User;
use App\Services\StageGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 1 — per-step blocking gate on elevation stages + strict-sequential gate
 * on WO job steps, with logged manager/office override.
 */
class StageGateTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<int,array<string,mixed>> $stageSpecs */
    private function elevationWithStages(array $stageSpecs): FdWoElevation
    {
        $job  = BusinessJob::create(['job_number' => 'J-' . uniqid(), 'job_name' => 'Job', 'status' => 'active']);
        $wo   = FdWorkOrder::create(['business_job_id' => $job->id, 'release_number' => 1]);
        $elev = FdWoElevation::create(['work_order_id' => $wo->id, 'elevation_tag' => 'E1']);

        foreach (array_values($stageSpecs) as $i => $spec) {
            FdWoStage::create([
                'elevation_id' => $elev->id,
                'name'         => $spec['name'] ?? "S{$i}",
                'sort_order'   => $spec['sort_order'] ?? ($i + 1),
                'blocks_next'  => $spec['blocks_next'] ?? true,
                'status'       => $spec['status'] ?? 'pending',
            ]);
        }

        return $elev->load('stages');
    }

    private function actingAsAdmin(string $name = 'Jane Office'): User
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true, 'name' => $name]);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ── service ──────────────────────────────────────────────────────────────

    public function test_non_blocking_predecessor_is_ignored(): void
    {
        $elev = $this->elevationWithStages([
            ['name' => 'Prep', 'status' => 'pending', 'blocks_next' => false],
            ['name' => 'Fab', 'status' => 'pending'],
        ]);

        $fab = $elev->stages->firstWhere('name', 'Fab');
        $this->assertNull(app(StageGateService::class)->blockingStageFor($fab));
    }

    public function test_incomplete_blocking_predecessor_blocks(): void
    {
        $elev = $this->elevationWithStages([
            ['name' => 'Material Check', 'status' => 'pending'],
            ['name' => 'Frame Fab', 'status' => 'pending'],
        ]);

        $blocking = app(StageGateService::class)
            ->blockingStageFor($elev->stages->firstWhere('name', 'Frame Fab'));

        $this->assertNotNull($blocking);
        $this->assertSame('Material Check', $blocking->name);
    }

    public function test_not_required_counts_as_terminal(): void
    {
        $elev = $this->elevationWithStages([
            ['name' => 'Material Check', 'status' => 'not_required'],
            ['name' => 'Frame Fab', 'status' => 'pending'],
        ]);

        $this->assertNull(app(StageGateService::class)
            ->blockingStageFor($elev->stages->firstWhere('name', 'Frame Fab')));
    }

    public function test_legacy_wo_scoped_stage_is_never_gated(): void
    {
        $job = BusinessJob::create(['job_number' => 'J-x', 'job_name' => 'J', 'status' => 'active']);
        $wo  = FdWorkOrder::create(['business_job_id' => $job->id, 'release_number' => 1]);
        $s   = FdWoStage::create(['work_order_id' => $wo->id, 'name' => 'Loose', 'sort_order' => 2, 'status' => 'pending']);

        $this->assertNull(app(StageGateService::class)->blockingStageFor($s));
    }

    // ── kiosk path (unauthenticated) ─────────────────────────────────────────

    public function test_kiosk_cycle_is_gated_and_returns_422(): void
    {
        $elev  = $this->elevationWithStages([
            ['name' => 'Material Check', 'status' => 'pending'],
            ['name' => 'Frame Fab', 'status' => 'pending'],
        ]);
        $frame = $elev->stages->firstWhere('name', 'Frame Fab');

        $this->patchJson("/api/v1/shop/stages/{$frame->id}", [])
            ->assertStatus(422)
            ->assertJson(['code' => 'stage_gated', 'blocking_stage' => ['name' => 'Material Check']]);

        $this->assertSame('pending', $frame->fresh()->status);
    }

    public function test_kiosk_cycle_allowed_when_predecessor_terminal(): void
    {
        $elev  = $this->elevationWithStages([
            ['name' => 'Material Check', 'status' => 'complete'],
            ['name' => 'Frame Fab', 'status' => 'pending'],
        ]);
        $frame = $elev->stages->firstWhere('name', 'Frame Fab');

        $this->patchJson("/api/v1/shop/stages/{$frame->id}", [])->assertOk();
        $this->assertSame('in_progress', $frame->fresh()->status);
    }

    public function test_reopening_a_completed_stage_is_never_gated(): void
    {
        $elev = $this->elevationWithStages([
            ['name' => 'Material Check', 'status' => 'pending'],
            ['name' => 'Frame Fab', 'status' => 'complete'],
        ]);
        $frame = $elev->stages->firstWhere('name', 'Frame Fab');

        // complete -> pending per the cycle map; must not be blocked
        $this->patchJson("/api/v1/shop/stages/{$frame->id}", [])->assertOk();
        $this->assertSame('pending', $frame->fresh()->status);
    }

    public function test_kiosk_manager_can_override_and_it_is_logged(): void
    {
        $mgr   = FdUser::create(['name' => 'Boss', 'role' => 'manager', 'active' => true]);
        $elev  = $this->elevationWithStages([
            ['name' => 'Material Check', 'status' => 'pending'],
            ['name' => 'Frame Fab', 'status' => 'pending'],
        ]);
        $frame = $elev->stages->firstWhere('name', 'Frame Fab');

        $this->patchJson("/api/v1/shop/stages/{$frame->id}", ['fab_user_id' => $mgr->id, 'override' => true])
            ->assertOk();

        $this->assertSame('in_progress', $frame->fresh()->status);
        $log = FdStageLog::where('stage_id', $frame->id)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Boss', $log->message);
    }

    public function test_kiosk_worker_cannot_override(): void
    {
        $worker = FdUser::create(['name' => 'Wk', 'role' => 'worker', 'active' => true]);
        $elev   = $this->elevationWithStages([
            ['name' => 'Material Check', 'status' => 'pending'],
            ['name' => 'Frame Fab', 'status' => 'pending'],
        ]);
        $frame  = $elev->stages->firstWhere('name', 'Frame Fab');

        $this->patchJson("/api/v1/shop/stages/{$frame->id}", ['fab_user_id' => $worker->id, 'override' => true])
            ->assertStatus(422);
    }

    public function test_bulk_complete_rolls_back_when_a_blocked_predecessor_remains(): void
    {
        $elev = $this->elevationWithStages([
            ['name' => 'Material Check', 'status' => 'on_hold'],
            ['name' => 'Frame Fab', 'status' => 'pending'],
            ['name' => 'QC', 'status' => 'pending'],
        ]);

        $this->patchJson("/api/v1/shop/elevations/{$elev->id}/complete-stages", [])
            ->assertStatus(422);

        // nothing changed — full rollback
        $this->assertSame(0, $elev->stages()->where('status', 'complete')->count());
        $this->assertSame('on_hold', $elev->stages()->where('name', 'Material Check')->value('status'));
    }

    public function test_bulk_complete_succeeds_in_order_when_ungated(): void
    {
        $elev = $this->elevationWithStages([
            ['name' => 'A', 'status' => 'pending'],
            ['name' => 'B', 'status' => 'pending'],
            ['name' => 'C', 'status' => 'pending'],
        ]);

        $this->patchJson("/api/v1/shop/elevations/{$elev->id}/complete-stages", [])
            ->assertOk()
            ->assertJson(['updated' => 3]);

        $this->assertSame(3, $elev->stages()->where('status', 'complete')->count());
    }

    // ── office path (authenticated) ──────────────────────────────────────────

    public function test_office_stage_update_rejects_unknown_status(): void
    {
        $this->actingAsAdmin();
        $elev = $this->elevationWithStages([['name' => 'S1', 'status' => 'pending']]);

        $this->patchJson("/api/v1/work-order-stages/{$elev->stages->first()->id}", ['status' => 'bogus'])
            ->assertStatus(422);
    }

    public function test_office_stage_update_is_gated_then_override_logs_actor(): void
    {
        $this->actingAsAdmin('Jane Office');
        $elev = $this->elevationWithStages([
            ['name' => 'A', 'status' => 'pending'],
            ['name' => 'B', 'status' => 'pending'],
        ]);
        $b = $elev->stages->firstWhere('name', 'B');

        $this->patchJson("/api/v1/work-order-stages/{$b->id}", ['status' => 'complete'])
            ->assertStatus(422)->assertJson(['code' => 'stage_gated']);

        $this->patchJson("/api/v1/work-order-stages/{$b->id}", ['status' => 'complete', 'override' => true])
            ->assertOk();

        $this->assertSame('complete', $b->fresh()->status);
        $log = FdStageLog::where('stage_id', $b->id)->first();
        $this->assertNotNull($log);
        $this->assertNull($log->user_id); // fd_stage_log FKs fd_users, office user name goes in message
        $this->assertStringContainsString('Jane Office', $log->message);
    }

    // ── job steps ───────────────────────────────────────────────────────────

    public function test_job_step_completion_is_strictly_sequential(): void
    {
        $this->actingAsAdmin();
        $job = BusinessJob::create(['job_number' => 'J-js', 'job_name' => 'J', 'status' => 'active']);
        $wo  = FdWorkOrder::create(['business_job_id' => $job->id, 'release_number' => 1]);
        $steps = $wo->steps()->orderBy('sort_order')->get(); // 4 seeded by FdWorkOrder::boot

        $this->patchJson("/api/v1/job-steps/{$steps[1]->id}", ['status' => 'complete'])
            ->assertStatus(422)->assertJson(['code' => 'stage_gated']);

        $this->patchJson("/api/v1/job-steps/{$steps[0]->id}", ['status' => 'complete'])->assertOk();
        $this->patchJson("/api/v1/job-steps/{$steps[1]->id}", ['status' => 'complete'])->assertOk();
    }

    public function test_job_step_complete_all_rolls_back_then_override(): void
    {
        $this->actingAsAdmin();
        $job = BusinessJob::create(['job_number' => 'J-ca', 'job_name' => 'J', 'status' => 'active']);
        $wo  = FdWorkOrder::create(['business_job_id' => $job->id, 'release_number' => 1]);
        $wo->steps()->orderBy('sort_order')->get()[1]->update(['status' => 'on_hold']);

        $this->patchJson("/api/v1/work-orders/{$wo->id}/steps/complete-all")->assertStatus(422);
        $this->assertSame(0, $wo->steps()->where('status', 'complete')->count());

        $this->patchJson("/api/v1/work-orders/{$wo->id}/steps/complete-all", ['override' => true])->assertOk();
        $this->assertSame(3, $wo->steps()->where('status', 'complete')->count());
    }
}
