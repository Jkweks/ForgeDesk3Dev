<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\ConfiguratorFrameProfile;
use App\Models\ConfiguratorFrameSeries;
use App\Models\ConfiguratorFrameSystem;
use App\Models\ConfiguratorHwlibBacker;
use App\Models\ConfiguratorHwlibCategory;
use App\Models\ConfiguratorHwlibItem;
use App\Models\ConfiguratorHwlibItemValue;
use App\Models\ConfiguratorHwlibLink;
use App\Models\ConfiguratorHwlibVariable;
use App\Models\ConfiguratorRule;
use App\Models\DoorFrameConfiguration;
use App\Models\DoorFrameFrameConfig;
use App\Models\DoorFrameOpeningSpec;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Configurator\HwlibResolver;
use App\Services\Configurator\RuleExpression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

/**
 * Configurator rules: "when this hardware / opening condition holds, change the BOM".
 * The worked case is the HES 9400 strike (P2454): it needs the 7" P2451 backer, which splits the
 * lock jamb stop in two around the panic centerline.
 */
class ConfiguratorRuleTest extends TestCase
{
    use RefreshDatabase;

    private ConfiguratorHwlibItem $strike;

    private ConfiguratorHwlibItem $panic;

    private ConfiguratorHwlibVariable $panicAff;

    private function product(string $pn, string $finish = 'C2'): Product
    {
        $supplier = Supplier::firstOrCreate(['name' => 'Rule Supplier']);
        $product = Product::create(['sku' => "SKU-{$pn}-{$finish}", 'description' => "Part {$pn}", 'supplier_id' => $supplier->id]);
        Product::where('id', $product->id)->update(['part_number' => $pn, 'finish' => $finish]);

        return $product->fresh();
    }

    /** A single 36 x 84 opening with a threshold (0.5") whose lock jamb stop is H - threshold = 83.5". */
    private function opening(bool $threshold = true, array $hardware = ['panic', 'strike']): DoorFrameConfiguration
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($user, ['*']);

        $system = ConfiguratorFrameSystem::create(['name' => '14000', 'code' => '14000']);
        $series = ConfiguratorFrameSeries::create(['frame_system_id' => $system->id, 'name' => 'Standard', 'code' => 'STANDARD']);
        $stopFormula = [['var' => 'H', 'sign' => 1], ['var' => 'if_threshold', 'sign' => -1]];
        foreach ([
            ['Door Head', 'E4500', [['var' => 'W', 'sign' => 1]], null, 1.75],
            ['Lock Jamb Stop', 'E4531', $stopFormula, 'single', 0.625],
            ['Hinge Jamb Stop', 'E4531', $stopFormula, 'single', 0.625],
            ['Threshold', 'E4600', [['var' => 'W', 'sign' => 1]], 'threshold', 0.5],
        ] as $i => [$role, $pn, $formula, $condition, $section]) {
            $product = Product::where('part_number', $pn)->first() ?? $this->product($pn);
            ConfiguratorFrameProfile::create([
                'frame_series_id' => $series->id, 'role_label' => $role, 'product_id' => $product->id, 'formula' => $formula,
                'condition' => $condition, 'section_height' => $section, 'qty_per_opening' => 1, 'sort_order' => $i,
            ]);
        }

        $panics = ConfiguratorHwlibCategory::create(['name' => 'Panic']);
        $strikes = ConfiguratorHwlibCategory::create(['name' => 'Strike']);
        $this->panicAff = ConfiguratorHwlibVariable::create(['code' => 'PANIC_CL_AFF', 'label' => 'Panic CL AFF', 'group_name' => 'Panic', 'var_type' => 'number', 'show_in_report' => true]);
        $panics->categoryVariables()->create(['variable_id' => $this->panicAff->id, 'sort_order' => 0]);

        $this->product('P2454');
        $this->product('P2451');
        $this->product('P9000');
        $this->panic = ConfiguratorHwlibItem::create(['category_id' => $panics->id, 'name' => 'Rim Panic', 'pn' => 'P9000']);
        ConfiguratorHwlibItemValue::create(['item_id' => $this->panic->id, 'variable_id' => $this->panicAff->id, 'value_text' => '40']);
        $this->strike = ConfiguratorHwlibItem::create(['category_id' => $strikes->id, 'name' => 'HES 9400', 'pn' => 'P2454']);
        ConfiguratorHwlibBacker::create(['pn' => 'P2451', 'description' => 'Electric Strike Backer', 'length' => 7]);

        $job = BusinessJob::create(['job_number' => 'RULE-1', 'job_name' => 'Rules job', 'status' => 'active']);
        $config = DoorFrameConfiguration::create(['business_job_id' => $job->id, 'job_scope' => 'frame_only', 'status' => 'draft', 'quantity' => 1]);
        DoorFrameOpeningSpec::create([
            'configuration_id' => $config->id, 'opening_type' => 'single', 'hand_single' => 'lhr', 'hinging' => 'butt',
            'door_opening_width' => 36, 'door_opening_height' => 84, 'finish' => 'C2', 'opening_angle' => 90,
        ]);
        DoorFrameFrameConfig::create(['configuration_id' => $config->id, 'frame_series_id' => $series->id, 'has_transom' => false, 'has_threshold' => $threshold]);

        foreach ($hardware as $which) {
            ConfiguratorHwlibLink::create(['configuration_id' => $config->id, 'item_id' => $this->{$which}->id, 'quantity' => 1, 'series' => 'Standard', 'leaf' => 'both']);
        }

        return $config;
    }

    private function strikeRule(array $overrides = []): ConfiguratorRule
    {
        return ConfiguratorRule::create($overrides + [
            'name' => 'HES 9400 backer',
            'enabled' => true,
            'conditions' => ['logic' => 'all', 'items' => [['type' => 'hardware', 'by' => 'pn', 'value' => 'p2454', 'present' => true]]],
            'actions' => [
                ['type' => 'add_part', 'scope' => 'hardware', 'pn' => 'P2451', 'label' => 'Electric Strike Backer', 'qty' => '1'],
                ['type' => 'split_part', 'scope' => 'frame', 'match' => ['by' => 'label', 'op' => 'eq', 'value' => 'Lock Jamb Stop'], 'pieces' => [
                    ['label' => 'Lower', 'length' => 'PANIC_CL_AFF - THRESHOLD - P2451.length / 2'],
                    ['label' => 'Upper', 'length' => 'ORIGINAL - PIECE1 - P2451.length'],
                ]],
            ],
        ]);
    }

    private function generate(DoorFrameConfiguration $config, string $what): array
    {
        return $this->postJson("/api/v1/door-frame-configurations/{$config->id}/{$what}-parts/generate")->assertOk()->json();
    }

    public function test_expression_evaluator(): void
    {
        $vars = ['A' => 10.0, 'B' => 4.0, 'P1.length' => 7.0];
        $eval = fn (string $e) => RuleExpression::evaluate($e, fn ($n) => $vars[$n] ?? null);

        $this->assertEqualsWithDelta(36.0, $eval('A * B - P1.length / 2 - 1/2'), 1e-9);
        $this->assertEqualsWithDelta(-6.0, $eval('-(A - B)'), 1e-9);
        $this->assertEqualsWithDelta(4.0, $eval('if(A > B, min(A, B), max(A, B))'), 1e-9);
        $this->assertEqualsWithDelta(10.125, $eval('round(10.13, 1/8)'), 1e-9);
        $this->assertSame(['A', 'P1.length'], RuleExpression::identifiers('max(A, 2) + P1.length'));
        $this->assertNull(RuleExpression::syntaxError('(A + B) * 2'));
        $this->assertNotNull(RuleExpression::syntaxError('A + * B'));
        $this->assertNotNull(RuleExpression::syntaxError('(A + B'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MISSING has no value');
        $eval('A + MISSING');
    }

    public function test_strike_rule_adds_the_backer_and_splits_the_lock_jamb_stop(): void
    {
        $config = $this->opening();
        $rule = $this->strikeRule();

        $frame = collect($this->generate($config, 'frame')['parts'])->keyBy('part_label');
        $this->assertArrayNotHasKey('Lock Jamb Stop', $frame->all(), 'the whole stop is replaced by its two pieces');
        // Lower: panic CL 40 - threshold 0.5 - half the 7" backer = 36. Upper: 83.5 - 36 - 7 = 40.5.
        $this->assertEqualsWithDelta(36.0, (float) $frame['Lock Jamb Stop — Lower']['calculated_length'], 1e-4);
        $this->assertEqualsWithDelta(40.5, (float) $frame['Lock Jamb Stop — Upper']['calculated_length'], 1e-4);
        $this->assertSame('HES 9400 backer', $frame['Lock Jamb Stop — Lower']['rule']);
        $this->assertSame('E4531', $frame['Lock Jamb Stop — Upper']['product']['part_number']);
        $this->assertEqualsWithDelta(83.5, (float) $frame['Hinge Jamb Stop']['calculated_length'], 1e-4, 'the hinge side is untouched');
        $this->assertNull($frame['Hinge Jamb Stop']['rule']);

        $hardware = collect($this->generate($config, 'hardware')['parts']);
        $backer = $hardware->firstWhere('part_label', 'Electric Strike Backer');
        $this->assertSame('P2451', $backer['product']['part_number']);
        $this->assertSame('backer', $backer['source_type']);
        $strikeLink = ConfiguratorHwlibLink::where('item_id', $this->strike->id)->value('id');
        $this->assertSame($strikeLink, $backer['hwlib_link_id'], 'the backer stays with the strike that called for it');
        $this->assertSame($rule->id, \App\Models\DoorFrameHardwarePart::where('part_label', 'Electric Strike Backer')->value('rule_id'));
    }

    public function test_without_a_threshold_the_lower_piece_runs_to_the_floor(): void
    {
        $config = $this->opening(threshold: false);
        $this->strikeRule();

        $frame = collect($this->generate($config, 'frame')['parts'])->keyBy('part_label');
        $this->assertEqualsWithDelta(36.5, (float) $frame['Lock Jamb Stop — Lower']['calculated_length'], 1e-4);
        $this->assertEqualsWithDelta(40.5, (float) $frame['Lock Jamb Stop — Upper']['calculated_length'], 1e-4);
    }

    public function test_rule_does_nothing_without_the_strike_or_when_disabled(): void
    {
        $config = $this->opening(hardware: ['panic']);
        $this->strikeRule();
        $labels = collect($this->generate($config, 'frame')['parts'])->pluck('part_label');
        $this->assertContains('Lock Jamb Stop', $labels);

        ConfiguratorHwlibLink::create(['configuration_id' => $config->id, 'item_id' => $this->strike->id, 'quantity' => 1, 'series' => 'Standard', 'leaf' => 'both']);
        ConfiguratorRule::query()->update(['enabled' => false]);
        $labels = collect($this->generate($config, 'frame')['parts'])->pluck('part_label');
        $this->assertContains('Lock Jamb Stop', $labels);
    }

    public function test_an_unresolved_value_skips_the_action_with_a_warning_instead_of_guessing(): void
    {
        // Strike but no panic: PANIC_CL_AFF has no value, so the stop must be left whole.
        $config = $this->opening(hardware: ['strike']);
        $this->strikeRule();

        $res = $this->generate($config, 'frame');
        $this->assertContains('Lock Jamb Stop', collect($res['parts'])->pluck('part_label'));
        $this->assertStringContainsString('PANIC_CL_AFF has no value', implode(' ', $res['warnings']));

        $status = $this->getJson("/api/v1/door-frame-configurations/{$config->id}/rule-status")->assertOk()->json();
        $this->assertStringContainsString('PANIC_CL_AFF', collect($status['messages'])->pluck('text')->implode(' '));
    }

    public function test_frame_bom_is_flagged_stale_when_hardware_changes_after_it_was_generated(): void
    {
        $config = $this->opening(hardware: ['panic']);
        $this->strikeRule();
        $this->generate($config, 'frame');
        $status = fn () => $this->getJson("/api/v1/door-frame-configurations/{$config->id}/rule-status")->assertOk()->json();
        $this->assertFalse($status()['stale']['frame']);

        // Adding the strike makes the rule fire: the saved frame BOM no longer matches.
        $link = ConfiguratorHwlibLink::create(['configuration_id' => $config->id, 'item_id' => $this->strike->id, 'quantity' => 1, 'series' => 'Standard', 'leaf' => 'both']);
        $this->assertTrue($status()['stale']['frame']);
        $this->assertSame(['HES 9400 backer'], collect($status()['fired'])->pluck('name')->all());

        $this->generate($config, 'frame');
        $this->assertFalse($status()['stale']['frame']);

        // Moving the panic centerline changes the split, so it is stale again.
        \App\Models\ConfiguratorHwlibLinkValue::create(['link_id' => ConfiguratorHwlibLink::where('item_id', $this->panic->id)->value('id'), 'variable_id' => $this->panicAff->id, 'value_text' => '42']);
        $this->assertTrue($status()['stale']['frame']);

        $preflight = $this->getJson("/api/v1/door-frame-configurations/{$config->id}/release-preflight")->assertOk()->json();
        $this->assertStringContainsString('Frame parts were generated before', implode(' ', $preflight['blockers']));

        $this->generate($config, 'frame');
        $link->delete();
        $this->assertTrue($status()['stale']['frame'], 'removing the strike must put the whole stop back');
    }

    public function test_other_part_actions_and_field_conditions(): void
    {
        $config = $this->opening();
        $this->product('E9999');
        ConfiguratorRule::create(['name' => 'Threshold tweaks', 'enabled' => true,
            'conditions' => ['logic' => 'all', 'items' => [
                ['type' => 'field', 'field' => 'has_threshold', 'op' => 'eq', 'value' => 'yes'],
                ['type' => 'compare', 'left' => 'W', 'op' => 'gte', 'right' => '36'],
                ['type' => 'hardware', 'by' => 'category', 'value' => 'Deadlock', 'present' => false],
            ]],
            'actions' => [
                ['type' => 'adjust_part', 'scope' => 'frame', 'match' => ['by' => 'label', 'op' => 'eq', 'value' => 'Threshold'], 'length' => 'ORIGINAL - 1/4', 'qty' => ''],
                ['type' => 'swap_part', 'scope' => 'frame', 'match' => ['by' => 'label', 'op' => 'contains', 'value' => 'hinge jamb'], 'pn' => 'E9999'],
                ['type' => 'remove_part', 'scope' => 'frame', 'match' => ['by' => 'pn', 'op' => 'eq', 'value' => 'E4500']],
                ['type' => 'add_part', 'scope' => 'frame', 'pn' => 'P2451', 'label' => 'Filler', 'qty' => 'if(HAS_THRESHOLD, 2, 1)', 'length' => 'section(\'Lock Jamb Stop\') + 1'],
            ],
        ]);

        $frame = collect($this->generate($config, 'frame')['parts'])->keyBy('part_label');
        $this->assertEqualsWithDelta(35.75, (float) $frame['Threshold']['calculated_length'], 1e-4);
        $this->assertSame('E9999', $frame['Hinge Jamb Stop']['product']['part_number']);
        $this->assertArrayNotHasKey('Door Head', $frame->all());
        $this->assertEqualsWithDelta(2.0, (float) $frame['Filler']['quantity'], 1e-4);
        $this->assertEqualsWithDelta(1.625, (float) $frame['Filler']['calculated_length'], 1e-4);

        // The same rule must not fire once a condition fails.
        ConfiguratorRule::query()->update(['conditions' => json_encode(['logic' => 'all', 'items' => [['type' => 'field', 'field' => 'opening_type', 'op' => 'eq', 'value' => 'pair']]])]);
        $this->assertContains('Door Head', collect($this->generate($config, 'frame')['parts'])->pluck('part_label'));
    }

    public function test_set_variable_feeds_prep_values_unless_the_link_overrides_it_and_messages_block_release(): void
    {
        $config = $this->opening();
        $pulls = ConfiguratorHwlibCategory::create(['name' => 'Push-Pull']);
        $pullAff = ConfiguratorHwlibVariable::create(['code' => 'PULL_CL_AFF', 'label' => 'Pull CL AFF', 'group_name' => 'Pull', 'var_type' => 'number', 'show_in_report' => true]);
        $pulls->categoryVariables()->create(['variable_id' => $pullAff->id, 'sort_order' => 0]);
        $pull = ConfiguratorHwlibItem::create(['category_id' => $pulls->id, 'name' => 'Pull Handle', 'pn' => null]);
        ConfiguratorHwlibItemValue::create(['item_id' => $pull->id, 'variable_id' => $pullAff->id, 'value_text' => '42']);
        $link = ConfiguratorHwlibLink::create(['configuration_id' => $config->id, 'item_id' => $pull->id, 'quantity' => 1, 'series' => 'Standard', 'leaf' => 'both']);

        ConfiguratorRule::create(['name' => 'Pull follows panic', 'enabled' => true,
            'conditions' => ['logic' => 'all', 'items' => [['type' => 'hardware', 'by' => 'category', 'value' => 'panic', 'present' => true]]],
            'actions' => [
                ['type' => 'set_variable', 'code' => 'PULL_CL_AFF', 'value' => 'PANIC_CL_AFF'],
                ['type' => 'message', 'severity' => 'error', 'text' => 'Check the pull with the panic.'],
            ],
        ]);

        $seen = [];
        $resolved = (new HwlibResolver($config->fresh()))->resolveVar('PULL_CL_AFF', $link->id, $seen);
        $this->assertSame('40', $resolved['value']);
        $this->assertSame('Pull follows panic', $resolved['rule']);

        \App\Models\ConfiguratorHwlibLinkValue::create(['link_id' => $link->id, 'variable_id' => $pullAff->id, 'value_text' => '44']);
        $seen = [];
        $this->assertSame('44', (new HwlibResolver($config->fresh()))->resolveVar('PULL_CL_AFF', $link->id, $seen)['value'], 'an opening\'s own override wins over a rule');

        $preflight = $this->getJson("/api/v1/door-frame-configurations/{$config->id}/release-preflight")->assertOk()->json();
        $this->assertContains('Pull follows panic: Check the pull with the panic.', $preflight['blockers']);
    }

    public function test_push_or_pull_items_only_get_their_own_side_of_the_prep_values(): void
    {
        $config = $this->opening(hardware: []);
        $category = ConfiguratorHwlibCategory::create(['name' => 'Push-Pull']);
        $mk = fn ($code, $side) => ConfiguratorHwlibVariable::create(['code' => $code, 'label' => $code, 'group_name' => 'PP', 'var_type' => 'number', 'show_in_report' => true, 'push_pull' => $side]);
        foreach ([$mk('PUSH_CL_AFF', 'push'), $mk('PULL_CL_AFF', 'pull'), $mk('PUSH_PULL_CONNECT', null)] as $i => $variable) {
            $category->categoryVariables()->create(['variable_id' => $variable->id, 'sort_order' => $i]);
        }
        $link = fn ($side) => ConfiguratorHwlibLink::create([
            'configuration_id' => $config->id, 'quantity' => 1, 'series' => 'Standard', 'leaf' => 'both',
            'item_id' => ConfiguratorHwlibItem::create(['category_id' => $category->id, 'name' => "Item {$side}", 'push_pull' => $side])->id,
        ]);
        [$push, $pull, $either] = [$link('push'), $link('pull'), $link(null)];

        $values = collect($this->getJson("/api/v1/door-frame-configurations/{$config->id}/hardware-values")->assertOk()->json('links'))
            ->mapWithKeys(fn ($l) => [$l['link_id'] => collect($l['values'])->pluck('code')->all()]);
        $this->assertSame(['PUSH_CL_AFF', 'PUSH_PULL_CONNECT'], $values[$push->id]);
        $this->assertSame(['PULL_CL_AFF', 'PUSH_PULL_CONNECT'], $values[$pull->id]);
        $this->assertSame(['PUSH_CL_AFF', 'PULL_CL_AFF', 'PUSH_PULL_CONNECT'], $values[$either->id]);

        $this->putJson("/api/v1/door-frame-configurations/{$config->id}/hardware-links/{$push->id}/values", ['values' => ['PULL_CL_AFF' => '40']])
            ->assertStatus(422);
        $this->putJson("/api/v1/door-frame-configurations/{$config->id}/hardware-links/{$push->id}/values", ['values' => ['PUSH_CL_AFF' => '40']])
            ->assertOk();
    }

    public function test_rule_crud_validates_the_definition(): void
    {
        $this->opening(hardware: []);

        $this->postJson('/api/v1/config/rules', ['name' => 'Bad', 'conditions' => null, 'actions' => [
            ['type' => 'split_part', 'scope' => 'frame', 'match' => ['by' => 'label', 'value' => 'Lock Jamb Stop'], 'pieces' => [['label' => 'A', 'length' => 'H + * 2']]],
            ['type' => 'set_variable', 'code' => 'NOPE', 'value' => '1'],
        ]])->assertStatus(422)->assertJsonValidationErrors('actions');

        $id = $this->postJson('/api/v1/config/rules', ['name' => 'Good', 'conditions' => ['logic' => 'all', 'items' => []], 'actions' => [
            ['type' => 'message', 'severity' => 'warning', 'text' => 'Heads up'],
        ]])->assertCreated()->json('rule.id');

        $this->putJson("/api/v1/config/rules/{$id}", ['name' => 'Renamed', 'enabled' => false, 'actions' => [['type' => 'message', 'severity' => 'warning', 'text' => 'Heads up']]])
            ->assertOk()->assertJsonPath('rule.enabled', false);
        $this->getJson('/api/v1/config/rules')->assertOk()->assertJsonPath('rules.0.name', 'Renamed')->assertJsonStructure(['meta' => ['fields', 'actions', 'scopes', 'built_ins']]);
        $this->deleteJson("/api/v1/config/rules/{$id}")->assertOk();
        $this->assertSame(0, ConfiguratorRule::count());
    }
}
