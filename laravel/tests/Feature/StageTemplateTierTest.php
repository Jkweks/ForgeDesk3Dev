<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\FdElevationType;
use App\Models\FdStageTemplate;
use App\Models\FdStageTemplateSet;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 2 — complexity tiers (fd_stage_template_sets) + elevation stage resync.
 */
class StageTemplateTierTest extends TestCase
{
    use RefreshDatabase;

    private FdElevationType $type;
    private FdStageTemplateSet $standard;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);

        $this->type = FdElevationType::create(['name' => 'CW', 'color' => '#f97316', 'sort_order' => 1, 'active' => true]);
        $this->standard = FdStageTemplateSet::create([
            'elevation_type_id' => $this->type->id,
            'name' => 'Standard', 'sort_order' => 0, 'is_default' => true,
        ]);
        $this->tpl($this->standard, 'Material Check', 1);
        $this->tpl($this->standard, 'Member Fab', 2);
        $this->tpl($this->standard, 'QC & Pack', 3, blocksNext: false);
    }

    private function tpl(FdStageTemplateSet $set, string $name, int $order, bool $blocksNext = true): FdStageTemplate
    {
        return FdStageTemplate::create([
            'elevation_type_id' => $set->elevation_type_id,
            'template_set_id'   => $set->id,
            'name'              => $name,
            'sort_order'        => $order,
            'blocks_next'       => $blocksNext,
        ]);
    }

    private function workOrder(): FdWorkOrder
    {
        $job = BusinessJob::create(['job_number' => 'J-' . uniqid(), 'job_name' => 'J', 'status' => 'active']);
        return FdWorkOrder::create(['business_job_id' => $job->id, 'release_number' => 1]);
    }

    // ── seeding ─────────────────────────────────────────────────────────────

    public function test_elevation_seeds_from_default_tier_and_copies_blocks_next(): void
    {
        $wo = $this->workOrder();

        $res = $this->postJson("/api/v1/work-orders/{$wo->id}/elevations", [
            'elevation_tag'     => 'CW-1',
            'elevation_type_id' => $this->type->id,
        ])->assertCreated();

        $res->assertJsonPath('template_set_id', $this->standard->id);
        $this->assertSame(['Material Check', 'Member Fab', 'QC & Pack'],
            collect($res->json('stages'))->pluck('name')->all());
        $this->assertSame([true, true, false],
            collect($res->json('stages'))->pluck('blocks_next')->all());
    }

    public function test_elevation_seeds_from_explicit_tier(): void
    {
        $adv = FdStageTemplateSet::create([
            'elevation_type_id' => $this->type->id, 'name' => 'Advanced', 'sort_order' => 1,
        ]);
        $this->tpl($adv, 'Material Check', 1);
        $this->tpl($adv, 'Thermal Break', 2);
        $this->tpl($adv, 'Member Fab', 3);
        $this->tpl($adv, 'Glazing Prep', 4);

        $wo = $this->workOrder();
        $res = $this->postJson("/api/v1/work-orders/{$wo->id}/elevations", [
            'elevation_tag'     => 'CW-2',
            'elevation_type_id' => $this->type->id,
            'template_set_id'   => $adv->id,
        ])->assertCreated();

        $res->assertJsonPath('template_set_id', $adv->id);
        $this->assertCount(4, $res->json('stages'));
    }

    // ── resync on tier bump ────────────────────────────────────────────────

    public function test_tier_bump_adds_carries_and_retires_stages(): void
    {
        $adv = FdStageTemplateSet::create(['elevation_type_id' => $this->type->id, 'name' => 'Advanced', 'sort_order' => 1]);
        $this->tpl($adv, 'Material Check', 1);   // carried (name match)
        $this->tpl($adv, 'Thermal Break', 2);    // added
        $this->tpl($adv, 'Member Fab', 3);       // carried

        $wo = $this->workOrder();
        $elevId = $this->postJson("/api/v1/work-orders/{$wo->id}/elevations", [
            'elevation_tag' => 'CW-3', 'elevation_type_id' => $this->type->id,
        ])->json('id');

        // start "Material Check" so it carries progress; leave the rest pending
        $elev = FdWoElevation::with('stages')->find($elevId);
        $elev->stages->firstWhere('name', 'Material Check')->update(['status' => 'in_progress', 'started_at' => now()]);

        $res = $this->patchJson("/api/v1/elevations/{$elevId}", ['template_set_id' => $adv->id])
            ->assertOk();

        $res->assertJsonPath('template_set_id', $adv->id);
        $summary = $res->json('resync_summary');
        $this->assertContains('Thermal Break', $summary['added']);
        $this->assertContains('Member Fab', $summary['carried']);
        $this->assertContains('QC & Pack', $summary['retired']);           // pending orphan -> not_required
        $this->assertContains('Material Check', $summary['carried']);

        $elev->load('stages');
        $this->assertSame('in_progress', $elev->stages->firstWhere('name', 'Material Check')->status);
        $this->assertSame('not_required', $elev->stages->firstWhere('name', 'QC & Pack')->status);
        $this->assertSame('pending', $elev->stages->firstWhere('name', 'Thermal Break')->status);
    }

    public function test_tier_bump_leaves_an_orphan_with_progress_untouched(): void
    {
        $lite = FdStageTemplateSet::create(['elevation_type_id' => $this->type->id, 'name' => 'Lite', 'sort_order' => 1]);
        $this->tpl($lite, 'Material Check', 1); // only step in the lean tier

        $wo = $this->workOrder();
        $elevId = $this->postJson("/api/v1/work-orders/{$wo->id}/elevations", [
            'elevation_tag' => 'CW-4', 'elevation_type_id' => $this->type->id,
        ])->json('id');

        $elev = FdWoElevation::with('stages')->find($elevId);
        $elev->stages->firstWhere('name', 'Member Fab')->update(['status' => 'complete', 'completed_at' => now()]);

        $summary = $this->patchJson("/api/v1/elevations/{$elevId}", ['template_set_id' => $lite->id])
            ->assertOk()->json('resync_summary');

        $this->assertContains('Member Fab', $summary['kept_with_progress']);
        $elev->load('stages');
        $this->assertSame('complete', $elev->stages->firstWhere('name', 'Member Fab')->status);
    }

    // ── tier CRUD ─────────────────────────────────────────────────────────

    public function test_tier_name_is_unique_per_type(): void
    {
        $this->postJson('/api/v1/stage-template-sets', [
            'elevation_type_id' => $this->type->id, 'name' => 'Standard',
        ])->assertStatus(422);
    }

    public function test_promoting_a_tier_demotes_the_previous_default(): void
    {
        $adv = FdStageTemplateSet::create(['elevation_type_id' => $this->type->id, 'name' => 'Advanced', 'sort_order' => 1]);

        $this->patchJson("/api/v1/stage-template-sets/{$adv->id}", ['is_default' => true])->assertOk();

        $this->assertTrue($adv->fresh()->is_default);
        $this->assertFalse($this->standard->fresh()->is_default);
    }

    public function test_cannot_delete_last_tier(): void
    {
        $this->deleteJson("/api/v1/stage-template-sets/{$this->standard->id}")->assertStatus(422);
    }

    public function test_delete_nonempty_tier_requires_force_then_moves_templates(): void
    {
        $adv = FdStageTemplateSet::create(['elevation_type_id' => $this->type->id, 'name' => 'Advanced', 'sort_order' => 1]);
        $moved = $this->tpl($adv, 'Extra Step', 1);

        $this->deleteJson("/api/v1/stage-template-sets/{$adv->id}")
            ->assertStatus(422)->assertJson(['code' => 'tier_not_empty']);

        $this->deleteJson("/api/v1/stage-template-sets/{$adv->id}?force=1")->assertOk();

        $this->assertDatabaseMissing('fd_stage_template_sets', ['id' => $adv->id]);
        $this->assertSame($this->standard->id, $moved->fresh()->template_set_id);
    }

    public function test_deleting_the_default_tier_promotes_a_sibling(): void
    {
        $adv = FdStageTemplateSet::create(['elevation_type_id' => $this->type->id, 'name' => 'Advanced', 'sort_order' => 1]);

        $this->deleteJson("/api/v1/stage-template-sets/{$this->standard->id}?force=1")->assertOk();

        $this->assertTrue($adv->fresh()->is_default);
    }

    // ── elevation-type integration ────────────────────────────────────────

    public function test_creating_an_elevation_type_auto_creates_a_default_tier(): void
    {
        $res = $this->postJson('/api/v1/elevation-types', ['name' => 'Curtainwall XL'])->assertCreated();

        $this->assertDatabaseHas('fd_stage_template_sets', [
            'elevation_type_id' => $res->json('id'),
            'name' => 'Standard',
            'is_default' => true,
        ]);
    }

    public function test_with_templates_payload_groups_sets_and_keeps_flat_default(): void
    {
        $adv = FdStageTemplateSet::create(['elevation_type_id' => $this->type->id, 'name' => 'Advanced', 'sort_order' => 1]);
        $this->tpl($adv, 'Only Advanced Step', 1);

        $payload = collect($this->getJson('/api/v1/elevation-types?with_templates=1')->json('elevation_types'))
            ->firstWhere('id', $this->type->id);

        $this->assertCount(2, $payload['stage_template_sets']);
        // flat key == default (Standard) tier's 3 steps
        $this->assertCount(3, $payload['stage_templates']);
    }

    public function test_stage_template_created_via_api_lands_in_named_tier_with_blocks_next(): void
    {
        $adv = FdStageTemplateSet::create(['elevation_type_id' => $this->type->id, 'name' => 'Advanced', 'sort_order' => 1]);

        $this->postJson('/api/v1/stage-templates', [
            'elevation_type_id' => $this->type->id,
            'template_set_id'   => $adv->id,
            'name'              => 'CNC',
            'blocks_next'       => false,
        ])->assertCreated()
          ->assertJsonPath('template.template_set_id', $adv->id)
          ->assertJsonPath('template.blocks_next', false);
    }
}
