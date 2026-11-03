<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\ConfiguratorHwlibCategory;
use App\Models\ConfiguratorHwlibItem;
use App\Models\ConfiguratorHwlibLink;
use App\Models\ConfiguratorHwlibSet;
use App\Models\ConfiguratorHwlibVariable;
use App\Models\DoorFrameConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Prep values on set-applied hardware: editing one from an opening can update the set's own defaults
 * (re-synced to every opening that follows it) instead of detaching that opening, and saving a set
 * from the admin screen must never wipe the defaults it already holds.
 */
class HwlibSetValuesTest extends TestCase
{
    use RefreshDatabase;

    private ConfiguratorHwlibSet $set;

    private ConfiguratorHwlibVariable $backset;

    private ConfiguratorHwlibItem $item;

    private DoorFrameConfiguration $a;

    private DoorFrameConfiguration $b;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);

        $category = ConfiguratorHwlibCategory::create(['name' => 'Locks']);
        $this->backset = ConfiguratorHwlibVariable::create(['code' => 'BACKSET', 'label' => 'Backset', 'group_name' => 'Prep', 'var_type' => 'number']);
        $category->categoryVariables()->create(['variable_id' => $this->backset->id, 'sort_order' => 0]);
        $this->item = ConfiguratorHwlibItem::create(['category_id' => $category->id, 'name' => 'Mortise Lock']);

        $job = BusinessJob::create(['job_number' => 'S-1', 'job_name' => 'Set Job', 'status' => 'active']);
        $this->a = DoorFrameConfiguration::create(['business_job_id' => $job->id, 'job_scope' => 'door_and_frame', 'status' => 'draft']);
        $this->b = DoorFrameConfiguration::create(['business_job_id' => $job->id, 'job_scope' => 'door_and_frame', 'status' => 'draft']);

        $this->set = ConfiguratorHwlibSet::create(['business_job_id' => $job->id, 'name' => 'Entry Set', 'is_pair' => false]);
        $this->putJson("/api/v1/config/hwlib-sets/{$this->set->id}/items", ['items' => [[
            'item_id' => $this->item->id, 'quantity' => 1, 'series' => 'Standard', 'leaf' => 'both',
            'values' => [['variable_id' => $this->backset->id, 'value_text' => '2.5']],
        ]]])->assertOk();
        $this->postJson("/api/v1/config/hwlib-sets/{$this->set->id}/apply", ['configuration_ids' => [$this->a->id, $this->b->id]])->assertOk();
    }

    private function link(DoorFrameConfiguration $c): ConfiguratorHwlibLink
    {
        return ConfiguratorHwlibLink::where('configuration_id', $c->id)->firstOrFail();
    }

    private function valueUrl(DoorFrameConfiguration $c): string
    {
        return "/api/v1/door-frame-configurations/{$c->id}/hardware-links/{$this->link($c)->id}/values";
    }

    private function backsetOn(DoorFrameConfiguration $c): ?string
    {
        return $this->link($c)->values()->where('variable_id', $this->backset->id)->value('value_text');
    }

    public function test_editing_without_confirmation_offers_updating_the_set(): void
    {
        $res = $this->putJson($this->valueUrl($this->a), ['values' => ['BACKSET' => '3']])->assertStatus(409);

        $res->assertJsonPath('code', 'set_decouple')->assertJsonPath('can_update_set', true)->assertJsonPath('set_opening_count', 2);
        $this->assertSame('2.5', $this->backsetOn($this->a));   // nothing changed yet
        $this->assertNotNull($this->link($this->a)->source_set_id);
    }

    public function test_update_set_writes_the_set_default_and_resyncs_every_opening(): void
    {
        $this->putJson($this->valueUrl($this->a), ['values' => ['BACKSET' => '3'], 'update_set' => true])
            ->assertOk()->assertJsonPath('set_updated', true)->assertJsonCount(2, 'reapplied_to');

        $this->assertSame('3', $this->set->setItems()->first()->values()->value('value_text'));
        foreach ([$this->a, $this->b] as $c) {
            $this->assertSame('3', $this->backsetOn($c));
            $this->assertSame($this->set->id, $this->link($c)->source_set_id);   // still following the set
        }
    }

    public function test_clearing_a_value_with_update_set_clears_the_set_default(): void
    {
        $this->putJson($this->valueUrl($this->a), ['values' => ['BACKSET' => ''], 'update_set' => true])->assertOk();

        $this->assertSame(0, $this->set->setItems()->first()->values()->count());
        $this->assertNull($this->backsetOn($this->b));
    }

    public function test_invalid_value_is_rejected_before_the_set_changes(): void
    {
        $this->putJson($this->valueUrl($this->a), ['values' => ['BACKSET' => 'abc'], 'update_set' => true])->assertStatus(422);

        $this->assertSame('2.5', $this->set->setItems()->first()->values()->value('value_text'));
    }

    public function test_detach_still_works_and_leaves_the_set_alone(): void
    {
        $this->putJson($this->valueUrl($this->a), ['values' => ['BACKSET' => '9'], 'confirm_decouple' => true])->assertOk();

        $this->assertSame('9', $this->backsetOn($this->a));
        $this->assertNull($this->link($this->a)->source_set_id);
        $this->assertSame('2.5', $this->set->setItems()->first()->values()->value('value_text'));
        $this->assertSame('2.5', $this->backsetOn($this->b));
    }

    public function test_released_openings_are_skipped_by_the_resync(): void
    {
        $this->b->update(['status' => 'released']);

        $this->putJson($this->valueUrl($this->a), ['values' => ['BACKSET' => '4'], 'update_set' => true])
            ->assertOk()->assertJsonPath('skipped_released.0', $this->b->id);

        $this->assertSame('4', $this->backsetOn($this->a));
        $this->assertSame('2.5', $this->backsetOn($this->b));
    }

    public function test_saving_set_items_without_values_keeps_the_existing_defaults(): void
    {
        $setItem = $this->set->setItems()->first();

        // The old admin screen sent no `values` at all — that used to wipe them (and every opening's copy).
        $this->putJson("/api/v1/config/hwlib-sets/{$this->set->id}/items", ['items' => [[
            'id' => $setItem->id, 'item_id' => $this->item->id, 'quantity' => 2, 'series' => 'Standard', 'leaf' => 'both',
        ]]])->assertOk();

        $fresh = $this->set->setItems()->first();
        $this->assertSame(2, $fresh->quantity);
        $this->assertSame('2.5', $fresh->values()->value('value_text'));
        $this->assertSame('2.5', $this->backsetOn($this->a));
    }

    public function test_saving_set_items_with_values_replaces_them(): void
    {
        $setItem = $this->set->setItems()->first();

        $this->putJson("/api/v1/config/hwlib-sets/{$this->set->id}/items", ['items' => [[
            'id' => $setItem->id, 'item_id' => $this->item->id, 'quantity' => 1, 'series' => 'Standard', 'leaf' => 'both',
            'values' => [['variable_id' => $this->backset->id, 'value_text' => '1.75']],
        ]]])->assertOk();

        $this->assertSame('1.75', $this->backsetOn($this->b));
    }
}
