<?php

namespace Tests\Feature;

use App\Models\CutlistRule;
use App\Models\User;
use App\Services\CutFlow\CutlistRuleEngine;
use App\Services\CutFlow\SillQuarterPointSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CutlistRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::connection('cutflow')->hasTable('cut_jobs')) {
            $this->artisan('migrate', ['--database' => 'cutflow', '--path' => 'database/migrations/cutflow', '--force' => true]);
        }
    }

    private function admin(): User
    {
        $u = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($u, ['*']);

        return $u;
    }

    private function cond(string $f, string $op, string $v = ''): array
    {
        return ['field' => $f, 'op' => $op, 'value' => $v];
    }

    private function rows(): array
    {
        $raw = fn ($pn, $len, $use, $bay) => ['part_id' => $pn, 'finish' => 'C2', 'dimension_in' => $len, 'qty' => 1, 'job' => 'J1', 'work_order' => 'W1', 'phase' => '1-E1', 'row' => $bay, 'column' => 0, 'description' => $use];

        return (new CutlistRuleEngine)->rowsFromRaw([
            $raw('T15001', '150', 'SILL', 1), $raw('T15001', '96 1/2', 'SILL', 2),
            $raw('T15356', '90', 'FEMALE VERTICAL', 1), $raw('T15300', '90', 'LEFT JAMB', ''),
        ]);
    }

    private function rule(string $kind, array $def, string $sev = 'error'): CutlistRule
    {
        return CutlistRule::create(['name' => "r-$kind", 'kind' => $kind, 'severity' => $sev, 'enabled' => true, 'definition' => $def]);
    }

    public function test_row_rule_flags_rows_failing_the_requirement(): void
    {
        $rule = $this->rule('row', [
            'filter' => ['logic' => 'all', 'items' => [$this->cond('use', 'eq', 'sill')]],
            'require' => ['logic' => 'all', 'items' => [$this->cond('length', 'lte', '144')]],
        ]);
        $f = (new CutlistRuleEngine)->evaluate($this->rows(), collect([$rule]));
        $this->assertCount(1, $f);
        $this->assertSame('error', $f[0]['severity']);
        $this->assertStringContainsString('150', $f[0]['message']);
    }

    public function test_companion_rule_requires_partner_in_same_bay(): void
    {
        $rule = $this->rule('companion', [
            'filter' => ['logic' => 'all', 'items' => [$this->cond('use', 'eq', 'SILL')]],
            'partner' => ['logic' => 'all', 'items' => [$this->cond('use', 'eq', 'FEMALE VERTICAL')]],
            'mode' => 'require', 'same_bay' => true,
        ]);
        $f = (new CutlistRuleEngine)->evaluate($this->rows(), collect([$rule]));
        $this->assertCount(1, $f); // bay 2 has no vertical
        $this->assertStringContainsString('bay 2', $f[0]['message']);
    }

    public function test_count_rule_per_elevation_with_precondition_and_custom_message(): void
    {
        $rule = $this->rule('count', [
            'when' => ['logic' => 'all', 'items' => [$this->cond('use', 'eq', 'SILL')]],
            'filter' => ['logic' => 'all', 'items' => [$this->cond('use', 'eq', 'RIGHT JAMB')]],
            'metric' => 'count', 'op' => 'eq', 'value' => 1,
        ], 'warning');
        $rule->message = 'Need {value} right jamb, found {actual}';
        $f = (new CutlistRuleEngine)->evaluate($this->rows(), collect([$rule]));
        $this->assertSame('Need 1 right jamb, found 0', $f[0]['message']);
        $this->assertSame('J1 / W1 / 1-E1', $f[0]['scope']);

        $rule->definition = ['when' => ['logic' => 'all', 'items' => [$this->cond('use', 'eq', 'DOOR')]]] + $rule->definition;
        $this->assertSame([], (new CutlistRuleEngine)->evaluate($this->rows(), collect([$rule]))); // no DOOR rows -> skipped
    }

    public function test_group_any_logic_and_between(): void
    {
        $rule = $this->rule('row', [
            'filter' => ['logic' => 'any', 'items' => [$this->cond('use', 'eq', 'LEFT JAMB'), $this->cond('use', 'eq', 'FEMALE VERTICAL')]],
            'require' => ['logic' => 'all', 'items' => [$this->cond('length', 'between', '95,100')]],
        ]);
        $this->assertCount(2, (new CutlistRuleEngine)->evaluate($this->rows(), collect([$rule])));
    }

    public function test_rule_api_is_admin_only_and_validates_definitions(): void
    {
        $u = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        Sanctum::actingAs($u, ['*']);
        $this->getJson('/api/v1/cutlist-rules')->assertForbidden();

        $this->admin();
        $this->postJson('/api/v1/cutlist-rules', ['name' => 'x', 'kind' => 'row', 'severity' => 'error', 'definition' => ['filter' => ['logic' => 'all', 'items' => []]]])
            ->assertStatus(422);
        $this->postJson('/api/v1/cutlist-rules', [
            'name' => 'Sill length', 'kind' => 'row', 'severity' => 'error',
            'definition' => ['require' => ['logic' => 'all', 'items' => [$this->cond('length', 'lte', '144')]]],
        ])->assertCreated();
        $this->getJson('/api/v1/cutlist-rules')->assertOk()->assertJsonCount(1, 'rules');
    }

    public function test_settings_overrides_feed_the_sill_service_and_reset(): void
    {
        $this->admin();
        $this->putJson('/api/v1/cutlist-rules/settings', [
            'left_gap' => 0.25, 'right_gap' => 0.125, 'roles' => ['sill' => 'subsill'], 'part_widths' => ['T99999' => 3],
        ])->assertOk();

        $eff = SillQuarterPointSettings::effective();
        $this->assertSame(0.25, (float) $eff['left_gap']);
        $this->assertSame('SUBSILL', $eff['roles']['sill']);
        $this->assertSame('FEMALE VERTICAL', $eff['roles']['female_vertical']);
        $this->assertEquals(3, $eff['part_widths']['T99999']);
        $this->assertEquals(4.5, $eff['part_widths']['T15361']); // config entries kept
        $this->assertNotContains('right_gap', SillQuarterPointSettings::overrides() ? array_keys(SillQuarterPointSettings::overrides()) : []); // equal to default => not stored

        $this->putJson('/api/v1/cutlist-rules/settings', ['fraction_denominator' => 10])->assertStatus(422);
        $this->deleteJson('/api/v1/cutlist-rules/settings')->assertOk();
        $this->assertSame(0.125, (float) SillQuarterPointSettings::effective()['left_gap']);
    }

    public function test_upload_blocked_by_error_rule_and_warning_rule_imports(): void
    {
        $this->admin();
        $csv = "T15001,C2,150,1,Job9,WO9,1-E1,1,0,SILL,0,0\nT15356,C2,90,1,Job9,WO9,1-E1,1,0,FEMALE VERTICAL,0,0\n";
        $mk = fn () => UploadedFile::fake()->createWithContent('j.csv', $csv);
        $def = ['filter' => ['logic' => 'all', 'items' => [$this->cond('use', 'eq', 'SILL')]], 'require' => ['logic' => 'all', 'items' => [$this->cond('length', 'lte', '144')]]];

        $rule = $this->rule('row', $def, 'error');
        $this->post('/cut-station/import', ['csv' => $mk()])->assertSessionHasErrors('csv');
        $this->assertFalse(\App\Models\CutFlow\CutJob::where('name', 'Job9')->exists());

        $rule->update(['severity' => 'warning']);
        $this->post('/cut-station/import', ['csv' => $mk()])->assertSessionHas('finish_warnings');
    }
}
