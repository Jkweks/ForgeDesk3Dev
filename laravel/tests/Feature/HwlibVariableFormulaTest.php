<?php

namespace Tests\Feature;

use App\Models\ConfiguratorHwlibVariable;
use App\Models\User;
use App\Services\Configurator\HwlibFormulaConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HwlibVariableFormulaTest extends TestCase
{
    use RefreshDatabase;

    private function var(string $code, array $extra = []): ConfiguratorHwlibVariable
    {
        return ConfiguratorHwlibVariable::create($extra + ['code' => $code, 'label' => $code, 'group_name' => 'G', 'var_type' => 'number']);
    }

    private function payload(string $code, array $def, array $extra = []): array
    {
        return $extra + ['code' => $code, 'label' => $code, 'group_name' => 'G', 'var_type' => 'number', 'is_calculated' => true, 'formula_def' => $def];
    }

    public function test_legacy_simple_formulas_convert(): void
    {
        $d = HwlibFormulaConverter::fromLegacy('X', 'MULL_WIDTH / 2');
        $this->assertSame([['sign' => 1, 'kind' => 'var', 'var' => 'MULL_WIDTH', 'mult' => 0.5]], $d['branches'][0]['terms']);

        $d = HwlibFormulaConverter::fromLegacy('X', 'THOLD_LZ + 3/16');
        $this->assertSame(['sign' => 1, 'kind' => 'fixed', 'value' => 0.1875], $d['branches'][0]['terms'][1]);

        // Parentheses distribute their sign.
        $d = HwlibFormulaConverter::fromLegacy('X', 'A - (B + C)');
        $this->assertSame([1, -1, -1], array_column($d['branches'][0]['terms'], 'sign'));
    }

    public function test_legacy_conditional_formulas_convert(): void
    {
        $d = HwlibFormulaConverter::fromLegacy('STRIKEMNT_ADD', 'Frame: STRIKE_Z · Stop: LOCK_STOP_Z + STRIKE_Z · Mortise: 0');
        $this->assertCount(3, $d['branches']);
        $this->assertSame(['type' => 'var_true', 'var' => 'STRIKEMNT_STOP'], $d['branches'][1]['when']);

        $d = HwlibFormulaConverter::fromLegacy('PANIC_BACKSET', 'Mullion: A + (B - C) + D · Standard: A - (B + C) + E');
        $this->assertSame('removable mullion', $d['branches'][0]['when']['match']);
        $this->assertNull($d['branches'][1]['when']);

        // Unknown conditional formulas are not guessed at.
        $this->assertNull(HwlibFormulaConverter::fromLegacy('OTHER', 'One: A · Two: B'));
    }

    public function test_api_saves_def_and_regenerates_text(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->var('A');

        $def = ['branches' => [['when' => null, 'terms' => [
            ['sign' => 1, 'kind' => 'var', 'var' => 'A', 'mult' => 0.5],
            ['sign' => -1, 'kind' => 'fixed', 'value' => 0.25],
        ]]]];
        $this->postJson('/api/v1/config/hwlib-variables', $this->payload('B', $def))->assertCreated();

        $b = ConfiguratorHwlibVariable::where('code', 'B')->first();
        $this->assertSame('A × 0.5 - 0.25', $b->formula);
        $this->assertSame($def, $b->formula_def);
    }

    public function test_api_rejects_unknown_and_circular_references(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $a = $this->var('A');
        $term = fn (string $v) => ['branches' => [['when' => null, 'terms' => [['sign' => 1, 'kind' => 'var', 'var' => $v, 'mult' => 1]]]]];

        $this->postJson('/api/v1/config/hwlib-variables', $this->payload('B', $term('NOPE')))->assertStatus(422);

        $b = $this->var('B', ['is_calculated' => true, 'formula_def' => $term('A')]);
        // A -> B -> A, and A -> A
        $this->putJson("/api/v1/config/hwlib-variables/{$a->id}", $this->payload('A', $term('B')))->assertStatus(422);
        $this->putJson("/api/v1/config/hwlib-variables/{$a->id}", $this->payload('A', $term('A')))->assertStatus(422);
    }

    public function test_unchecking_calculated_clears_formula(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->var('A');
        $b = $this->var('B', ['is_calculated' => true, 'formula' => 'A', 'formula_def' => HwlibFormulaConverter::fromLegacy('B', 'A')]);

        $this->putJson("/api/v1/config/hwlib-variables/{$b->id}", ['code' => 'B', 'label' => 'B', 'group_name' => 'G', 'var_type' => 'number', 'is_calculated' => false])->assertOk();
        $b->refresh();
        $this->assertNull($b->formula_def);
        $this->assertNull($b->formula);
    }
}
