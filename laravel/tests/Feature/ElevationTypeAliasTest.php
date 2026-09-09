<?php

namespace Tests\Feature;

use App\Models\FdElevationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Linked names (aliases) on elevation types — the strings a work-order import
 * row's "Type" cell may use to resolve to a type.
 */
class ElevationTypeAliasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
    }

    public function test_store_persists_and_cleans_aliases(): void
    {
        $r = $this->postJson('/api/v1/elevation-types', [
            'name' => 'CW',
            'aliases' => ['Curtainwall', '  Curtain Wall  ', 'curtainwall', '', 'CW'],
        ])->assertCreated();

        $type = FdElevationType::find($r->json('id'));

        // trimmed, blanks dropped, case-insensitive de-dupe (first wins),
        // and an alias that just repeats the name is dropped.
        $this->assertSame(['Curtainwall', 'Curtain Wall'], $type->aliases);
    }

    public function test_update_replaces_alias_list(): void
    {
        $type = FdElevationType::create(['name' => 'SF', 'color' => '#3b82f6', 'sort_order' => 1, 'active' => true]);

        $this->putJson("/api/v1/elevation-types/{$type->id}", [
            'name' => 'SF',
            'aliases' => ['Storefront', 'Store Front'],
        ])->assertOk();

        $this->assertSame(['Storefront', 'Store Front'], $type->fresh()->aliases);

        $this->putJson("/api/v1/elevation-types/{$type->id}", [
            'name' => 'SF',
            'aliases' => [],
        ])->assertOk();

        $this->assertSame([], $type->fresh()->aliases);
    }

    public function test_index_exposes_aliases(): void
    {
        FdElevationType::create([
            'name' => 'CW', 'color' => '#f97316', 'sort_order' => 1, 'active' => true,
            'aliases' => ['Curtainwall', 'CWall'],
        ]);

        $this->getJson('/api/v1/elevation-types')
            ->assertOk()
            ->assertJsonPath('elevation_types.0.aliases', ['Curtainwall', 'CWall']);
    }

    public function test_match_terms_includes_name_and_aliases_lowercased(): void
    {
        $type = FdElevationType::create([
            'name' => 'CW', 'color' => '#f97316', 'sort_order' => 1, 'active' => true,
            'aliases' => ['Curtainwall', 'Curtain Wall', 'CWall'],
        ]);

        $this->assertSame(['cw', 'curtainwall', 'curtain wall', 'cwall'], $type->matchTerms());
    }
}
