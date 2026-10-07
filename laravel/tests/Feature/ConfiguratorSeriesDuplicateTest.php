<?php

namespace Tests\Feature;

use App\Models\ConfiguratorFrameComponent;
use App\Models\ConfiguratorFrameFastener;
use App\Models\ConfiguratorFrameProfile;
use App\Models\ConfiguratorFrameSeries;
use App\Models\ConfiguratorFrameSystem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfiguratorSeriesDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private ConfiguratorFrameSystem $system;

    private ConfiguratorFrameSeries $series;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);

        $supplier = Supplier::create(['name' => 'Series Supplier']);
        $product = fn (string $sku) => Product::create(['sku' => $sku, 'description' => $sku, 'supplier_id' => $supplier->id]);
        $head = $product('HEAD-1');
        $jamb = $product('JAMB-1');
        $clip = $product('CLIP-1');
        $screw = $product('SCREW-1');

        $this->system = ConfiguratorFrameSystem::create(['name' => 'Storefront', 'code' => 'SF']);
        $this->series = ConfiguratorFrameSeries::create(['frame_system_id' => $this->system->id, 'name' => 'Standard', 'code' => 'STD', 'sort_order' => 1]);

        $headProfile = ConfiguratorFrameProfile::create([
            'frame_series_id' => $this->series->id, 'role_label' => 'Head', 'product_id' => $head->id, 'sort_order' => 1,
            'section_height' => 4.5, 'qty_per_opening' => 1, 'condition' => 'single', 'glass_thicknesses' => ['0.25', '0.5'],
            'formula' => [['sign' => 1, 'var' => 'opening_width'], ['sign' => -1, 'var' => 'fixed', 'value' => 0.25]],
        ]);
        $jambProfile = ConfiguratorFrameProfile::create([
            'frame_series_id' => $this->series->id, 'role_label' => 'Jamb', 'product_id' => $jamb->id, 'sort_order' => 2,
            // Formulas refer to other profiles by role label, so a copy stays self-contained.
            'formula' => [['sign' => 1, 'var' => 'opening_height'], ['sign' => -1, 'var' => 'section:Head']],
        ]);
        $component = ConfiguratorFrameComponent::create(['frame_profile_id' => $headProfile->id, 'label' => 'Corner clip', 'product_id' => $clip->id, 'qty_type' => 'per_opening', 'qty_per' => 2, 'sort_order' => 1]);
        ConfiguratorFrameFastener::create(['frame_component_id' => $component->id, 'label' => 'Clip screw', 'product_id' => $screw->id, 'qty_per' => 4, 'sort_order' => 1]);
        ConfiguratorFrameComponent::create(['frame_profile_id' => $jambProfile->id, 'label' => 'Jamb shim', 'product_id' => $clip->id, 'qty_type' => 'per_length', 'qty_per' => 0.5, 'sort_order' => 1]);
    }

    private function duplicate(array $payload = [], ?int $id = null)
    {
        return $this->postJson('/api/v1/config/frame-series/'.($id ?? $this->series->id).'/duplicate', $payload + ['name' => 'Standard Thermal', 'code' => 'STD-T']);
    }

    public function test_a_series_is_copied_with_its_whole_profile_component_and_fastener_tree(): void
    {
        $response = $this->duplicate()->assertCreated();

        $this->assertSame('Series duplicated (2 profiles, 2 components, 1 fasteners).', $response->json('message'));
        $this->assertSame(['profiles' => 2, 'components' => 2, 'fasteners' => 1], $response->json('copied'));

        $copy = ConfiguratorFrameSeries::with('profiles.components.fasteners')->findOrFail($response->json('frame_series.id'));
        $this->assertNotSame($this->series->id, $copy->id);
        $this->assertSame([$this->system->id, 'Standard Thermal', 'STD-T'], [$copy->frame_system_id, $copy->name, $copy->code], 'stays in the same frame system');

        $original = ConfiguratorFrameSeries::with('profiles.components.fasteners')->find($this->series->id);
        $this->assertCount(2, $copy->profiles);

        foreach ($original->profiles as $i => $profile) {
            $newProfile = $copy->profiles[$i];
            $this->assertNotSame($profile->id, $newProfile->id, 'a real copy, not shared rows');
            foreach (['role_label', 'product_id', 'formula', 'condition', 'glass_thicknesses', 'section_height', 'qty_per_opening', 'sort_order'] as $field) {
                $this->assertEquals($profile->$field, $newProfile->$field, "profile {$profile->role_label} {$field}");
            }
            $this->assertCount($profile->components->count(), $newProfile->components);

            foreach ($profile->components as $j => $component) {
                $newComponent = $newProfile->components[$j];
                foreach (['label', 'product_id', 'qty_type', 'qty_per', 'sort_order'] as $field) {
                    $this->assertEquals($component->$field, $newComponent->$field, "component {$component->label} {$field}");
                }
                foreach ($component->fasteners as $k => $fastener) {
                    foreach (['label', 'product_id', 'qty_per'] as $field) {
                        $this->assertEquals($fastener->$field, $newComponent->fasteners[$k]->$field, "fastener {$fastener->label} {$field}");
                    }
                }
            }
        }
    }

    public function test_the_original_is_untouched_and_the_copy_is_independent(): void
    {
        $copyId = $this->duplicate()->assertCreated()->json('frame_series.id');

        $this->assertSame(2, ConfiguratorFrameProfile::where('frame_series_id', $this->series->id)->count());
        $this->assertSame(2, ConfiguratorFrameProfile::where('frame_series_id', $copyId)->count());

        // Editing the copy's profile leaves the original's alone, and deleting the copy removes only its own tree.
        ConfiguratorFrameProfile::where('frame_series_id', $copyId)->where('role_label', 'Head')->update(['role_label' => 'Head (thermal)']);
        $this->assertTrue(ConfiguratorFrameProfile::where('frame_series_id', $this->series->id)->where('role_label', 'Head')->exists());

        $this->deleteJson("/api/v1/config/frame-series/{$copyId}")->assertOk();
        $this->assertSame(2, ConfiguratorFrameProfile::where('frame_series_id', $this->series->id)->count());
        $this->assertSame(2, ConfiguratorFrameComponent::whereIn('frame_profile_id', ConfiguratorFrameProfile::where('frame_series_id', $this->series->id)->pluck('id'))->count());
    }

    public function test_the_copy_appears_in_the_catalog_tree_after_the_original(): void
    {
        $this->duplicate()->assertCreated();

        $tree = $this->getJson('/api/v1/config/catalog/tree')->assertOk()->json('frame_systems');
        $system = collect($tree)->firstWhere('id', $this->system->id);
        $this->assertSame(['STD', 'STD-T'], array_column($system['series'], 'code'));
    }

    public function test_code_clashes_and_bad_input_are_rejected_with_clear_messages(): void
    {
        $this->duplicate(['code' => 'STD'])->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'STD') && str_contains($m, 'already exists'));
        $this->duplicate(['name' => ''])->assertStatus(422);
        $this->duplicate(['code' => str_repeat('X', 31)])->assertStatus(422);
        $this->postJson('/api/v1/config/frame-series/999999/duplicate', ['name' => 'x', 'code' => 'y'])->assertNotFound();

        $this->assertSame(1, ConfiguratorFrameSeries::where('frame_system_id', $this->system->id)->count(), 'nothing was created');
    }

    public function test_the_same_code_may_exist_in_a_different_frame_system(): void
    {
        $other = ConfiguratorFrameSystem::create(['name' => 'Curtainwall', 'code' => 'CW']);
        ConfiguratorFrameSeries::create(['frame_system_id' => $other->id, 'name' => 'Other', 'code' => 'STD-T']);

        $this->duplicate()->assertCreated(); // STD-T is only taken in the other system
    }

    public function test_only_catalog_managers_can_duplicate_a_series(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]), ['*']);

        $this->duplicate()->assertForbidden();
        $this->assertSame(1, ConfiguratorFrameSeries::where('frame_system_id', $this->system->id)->count());
    }

    public function test_the_admin_page_offers_duplicate_on_each_series(): void
    {
        $html = $this->get('/config/admin')->assertOk()->getContent();

        $this->assertStringContainsString('cfgOpenDuplicateSeriesModal(', $html);
        $this->assertStringContainsString('/duplicate`', $html);
        $this->assertStringContainsString('id="cfg-series-dup-hint"', $html);
        $this->assertStringContainsString('id="cfg-series-modal-title"', $html);
    }
}
