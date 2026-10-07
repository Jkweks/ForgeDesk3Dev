<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\ConfiguratorHwlibCategory;
use App\Models\ConfiguratorHwlibItem;
use App\Models\ConfiguratorHwlibItemValue;
use App\Models\ConfiguratorHwlibLink;
use App\Models\ConfiguratorHwlibLinkValue;
use App\Models\ConfiguratorHwlibVariable;
use App\Models\DoorFrameConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfiguratorHardwareSectionsTest extends TestCase
{
    use RefreshDatabase;

    private DoorFrameConfiguration $config;

    private ConfiguratorHwlibLink $stdLink;

    private ConfiguratorHwlibLink $customLink;

    /** @var array<string, ConfiguratorHwlibVariable> */
    private array $vars = [];

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);

        $category = ConfiguratorHwlibCategory::create(['name' => 'Test Closer']);
        $defs = [
            'T_NUM' => ['Backset', 'number', 'in', []],
            'T_SEL' => ['Arm Type', 'select', null, ['Regular', 'Parallel']],
            'T_BOOL' => ['Reinforced', 'boolean', null, []],
            'T_TXT' => ['Note', 'text', null, []],
            'T_MAT' => ['Hinge Offset', 'degree_matrix', 'in', []],
        ];
        foreach (array_values($defs) as $i => [$label, $type, $unit, $options]) {
            $code = array_keys($defs)[$i];
            $this->vars[$code] = ConfiguratorHwlibVariable::create([
                'code' => $code, 'label' => $label, 'group_name' => 'Prep', 'var_type' => $type, 'unit' => $unit,
                'options' => $options, 'show_in_report' => true, 'sort_order' => $i,
            ]);
            $category->categoryVariables()->create(['variable_id' => $this->vars[$code]->id, 'sort_order' => $i]);
        }

        $standard = ConfiguratorHwlibItem::create(['category_id' => $category->id, 'name' => 'Std Closer', 'vos_standard' => true]);
        $custom = ConfiguratorHwlibItem::create(['category_id' => $category->id, 'name' => 'Custom Closer', 'vos_standard' => false]);
        ConfiguratorHwlibItemValue::create(['item_id' => $standard->id, 'variable_id' => $this->vars['T_NUM']->id, 'value_text' => '5']);

        $job = BusinessJob::create(['job_number' => 'HW-1', 'job_name' => 'Hardware Job', 'status' => 'active']);
        $this->config = DoorFrameConfiguration::create(['business_job_id' => $job->id, 'job_scope' => 'door_and_frame', 'status' => 'draft']);
        $this->stdLink = ConfiguratorHwlibLink::create(['configuration_id' => $this->config->id, 'item_id' => $standard->id, 'quantity' => 1, 'series' => 'Standard', 'leaf' => 'both']);
        $this->customLink = ConfiguratorHwlibLink::create(['configuration_id' => $this->config->id, 'item_id' => $custom->id, 'quantity' => 1, 'series' => 'Standard', 'leaf' => 'both']);
    }

    private function url(ConfiguratorHwlibLink $link, string $suffix = 'values'): string
    {
        return "/api/v1/door-frame-configurations/{$this->config->id}/hardware-links/{$link->id}/{$suffix}";
    }

    private function resolved(ConfiguratorHwlibLink $link): array
    {
        $links = $this->getJson("/api/v1/door-frame-configurations/{$this->config->id}/hardware-values")->assertOk()->json('links');

        return collect($links)->firstWhere('link_id', $link->id);
    }

    private function row(ConfiguratorHwlibLink $link, string $code): ?array
    {
        return collect($this->resolved($link)['values'])->firstWhere('code', $code);
    }

    public function test_resolved_values_carry_what_the_editor_needs_and_flag_standard_links(): void
    {
        $std = $this->resolved($this->stdLink);

        $this->assertTrue($std['vos_standard']);
        $this->assertFalse($this->resolved($this->customLink)['vos_standard']);
        $this->assertSame('Test Closer', $std['category']);

        $num = $this->row($this->stdLink, 'T_NUM');
        $this->assertSame(['5', false, 'number', $this->vars['T_NUM']->id], [$num['value'], $num['overridden'], $num['var_type'], $num['variable_id']]);
        $this->assertSame(['Regular', 'Parallel'], $this->row($this->stdLink, 'T_SEL')['options']);

        // The configuration detail marks which links are standard (the UI splits sections on this).
        $detail = $this->getJson("/api/v1/door-frame-configurations/{$this->config->id}")->assertOk()->json('configuration.hardware_links');
        $this->assertSame([true, false], collect($detail)->sortBy('id')->pluck('item.vos_standard')->values()->all());
    }

    public function test_a_prep_value_can_be_overridden_for_this_configuration_and_reset_to_the_catalog_value(): void
    {
        $this->putJson($this->url($this->stdLink), ['values' => ['T_NUM' => '7.5']])->assertOk()->assertJsonPath('bom_stale', true);

        $row = $this->row($this->stdLink, 'T_NUM');
        $this->assertSame(['7.5', true], [$row['value'], $row['overridden']]);
        $this->assertSame('5', ConfiguratorHwlibItemValue::where('variable_id', $this->vars['T_NUM']->id)->value('value_text'), 'the catalog is not touched');

        // The other (custom) link is independent.
        $this->assertNull(ConfiguratorHwlibLinkValue::where('link_id', $this->customLink->id)->first());

        $this->putJson($this->url($this->stdLink), ['values' => ['T_NUM' => '']])->assertOk();
        $row = $this->row($this->stdLink, 'T_NUM');
        $this->assertSame(['5', false], [$row['value'], $row['overridden']]);
        $this->assertNull(ConfiguratorHwlibLinkValue::where('link_id', $this->stdLink->id)->first());
    }

    public function test_every_variable_type_validates_and_saves(): void
    {
        $ok = ['T_SEL' => 'Parallel', 'T_BOOL' => 'true', 'T_TXT' => 'Field verify', 'T_NUM' => '2'];
        $this->putJson($this->url($this->customLink), ['values' => $ok])->assertOk();
        foreach ($ok as $code => $expected) {
            $this->assertSame($expected, $this->row($this->customLink, $code)['value'], $code);
        }

        $rejects = [
            ['T_NUM', 'abc', 'must be a number'],
            ['T_MAT', 'x', 'must be a number'],
            ['T_BOOL', 'maybe', 'Yes or No'],
            ['T_SEL', 'Sideways', 'listed options'],
        ];
        foreach ($rejects as [$code, $value, $reason]) {
            $this->putJson($this->url($this->customLink), ['values' => [$code => $value]])
                ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, $reason));
        }
        $this->putJson($this->url($this->customLink), ['values' => ['NOT_A_CODE' => '1']])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'NOT_A_CODE'));
        // A rejected save changes nothing.
        $this->assertSame('2', $this->row($this->customLink, 'T_NUM')['value']);
    }

    public function test_a_degree_matrix_override_sets_the_cell_at_the_openings_angle_and_keeps_other_angles(): void
    {
        ConfiguratorHwlibLinkValue::create(['link_id' => $this->stdLink->id, 'variable_id' => $this->vars['T_MAT']->id, 'value_text' => json_encode(['85' => '9'])]);

        $this->putJson($this->url($this->stdLink), ['values' => ['T_MAT' => '1.25']])->assertOk();

        $stored = json_decode(ConfiguratorHwlibLinkValue::where('link_id', $this->stdLink->id)->where('variable_id', $this->vars['T_MAT']->id)->value('value_text'), true);
        $this->assertSame(['85' => '9', '90' => '1.25'], $stored, 'default opening angle is 90; the other angle survives');
        $row = $this->row($this->stdLink, 'T_MAT');
        $this->assertSame(['1.25', true], [$row['value'], $row['overridden']]);
    }

    public function test_prep_values_follow_the_same_edit_locks_and_permissions_as_the_rest_of_the_configuration(): void
    {
        $this->config->update(['status' => 'released']);
        $this->putJson($this->url($this->stdLink), ['values' => ['T_NUM' => '9']])->assertStatus(422);

        $this->config->update(['status' => 'draft']);
        Sanctum::actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]), ['*']);
        $this->putJson($this->url($this->stdLink), ['values' => ['T_NUM' => '9']])->assertForbidden();
        $this->assertNull(ConfiguratorHwlibLinkValue::where('link_id', $this->stdLink->id)->first());

        // A link that belongs to a different configuration cannot be edited through this one.
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $other = DoorFrameConfiguration::create(['business_job_id' => $this->config->business_job_id, 'job_scope' => 'door_and_frame', 'status' => 'draft']);
        $foreign = ConfiguratorHwlibLink::create(['configuration_id' => $other->id, 'item_id' => $this->stdLink->item_id, 'quantity' => 1, 'series' => 'Standard', 'leaf' => 'both']);
        $this->putJson($this->url($foreign), ['values' => ['T_NUM' => '9']])->assertNotFound();
    }

    public function test_hardware_tab_has_nested_standard_and_custom_sections(): void
    {
        $html = $this->get('/config')->assertOk()->getContent();

        foreach (['hws' => 'fb-hws-selection', 'hwc' => 'fb-hwc-add'] as $prefix => $first) {
            foreach ([$first, "fb-{$prefix}-linked", "fb-{$prefix}-values", "fb-{$prefix}-bom", "fb-{$prefix}-links-tbody", "fb-{$prefix}-resolved", "fb-{$prefix}-parts-tbody", "fb-{$prefix}-bom-stale"] as $id) {
                $this->assertStringContainsString('id="'.$id.'"', $html, "missing #{$id}");
            }
        }
        // The old shared (unscoped) sections are gone.
        foreach (['fb-hw-links-tbody', 'fb-hw-resolved-wrap', 'fb-hw-parts-tbody'] as $old) {
            $this->assertStringNotContainsString('id="'.$old.'"', $html);
        }
        $this->assertStringContainsString('id="fb-hardware-add-form"', $html, 'the add form moved into Custom > Add Hardware');
        $this->assertStringContainsString('id="fb-hws-sections"', $html, 'the standard selection table is still there');
    }
}
