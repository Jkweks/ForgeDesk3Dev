<?php

namespace Tests\Feature;

use App\Models\CompanyLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyLocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
    }

    public function test_migration_seeds_exactly_one_primary_location(): void
    {
        $this->assertSame(1, CompanyLocation::count());
        $this->assertSame(1, CompanyLocation::where('is_primary', true)->count());
    }

    public function test_creating_a_primary_location_demotes_the_previous_primary(): void
    {
        $original = CompanyLocation::primary()->first();

        $res = $this->postJson('/api/v1/company-locations', [
            'name' => 'North Plant',
            'is_primary' => true,
            'city' => 'Toledo',
            'state' => 'OH',
        ])->assertCreated();

        $this->assertTrue((bool) $res->json('location.is_primary'));
        $this->assertFalse($original->fresh()->is_primary);
        $this->assertSame(1, CompanyLocation::where('is_primary', true)->count());
    }

    public function test_deleting_the_primary_promotes_another_location(): void
    {
        $primary = CompanyLocation::primary()->first();
        $other = CompanyLocation::create(['name' => 'Annex', 'is_primary' => false]);

        $this->deleteJson("/api/v1/company-locations/{$primary->id}")->assertOk();

        $this->assertTrue($other->fresh()->is_primary);
    }

    public function test_cannot_delete_the_last_remaining_location(): void
    {
        $only = CompanyLocation::primary()->first();

        $this->deleteJson("/api/v1/company-locations/{$only->id}")
            ->assertStatus(422);

        $this->assertSame(1, CompanyLocation::count());
    }

    public function test_make_primary_via_update_moves_the_flag(): void
    {
        $primary = CompanyLocation::primary()->first();
        $other = CompanyLocation::create(['name' => 'Warehouse 2', 'is_primary' => false]);

        $this->patchJson("/api/v1/company-locations/{$other->id}", [
            'name' => 'Warehouse 2',
            'is_primary' => true,
        ])->assertOk();

        $this->assertTrue($other->fresh()->is_primary);
        $this->assertFalse($primary->fresh()->is_primary);
    }

    public function test_requires_settings_permission(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'worker', 'is_active' => true]), ['*']);

        $this->getJson('/api/v1/company-locations')->assertStatus(403);
        $this->postJson('/api/v1/company-locations', ['name' => 'X'])->assertStatus(403);
    }
}
