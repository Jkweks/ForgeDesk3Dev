<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Universal search (docs/plans/pm-suite-integration.md, feature #26). */
class UniversalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_across_groups_and_respects_scope(): void
    {
        $sarah = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $mine = BusinessJob::withoutGlobalScopes()->create(['job_number' => '7777', 'job_name' => 'Wegener Hall', 'status' => 'bidding', 'project_manager_id' => $sarah->id]);
        BusinessJob::withoutGlobalScopes()->create(['job_number' => '7778', 'job_name' => 'Wegener Annex', 'status' => 'bidding', 'project_manager_id' => $other->id]);
        $vendor = Supplier::create(['name' => 'Wegener Glass', 'is_active' => true, 'bidestimate_code' => 'Weg $']);

        Sanctum::actingAs($sarah, ['*']);
        $groups = collect($this->getJson('/api/v1/search?q=wegener')->assertOk()->json('groups'))->keyBy('key');
        $titles = collect($groups['pm_jobs']['items'])->pluck('title');
        $this->assertTrue($titles->contains('7777 — Wegener Hall'));
        $this->assertFalse($titles->contains('7778 — Wegener Annex'));       // someone else's bid
        $this->assertSame("/pm/jobs/{$mine->id}", $groups['pm_jobs']['items'][0]['href']);
        $this->assertTrue($groups->has('vendors'));                          // project_manager can see vendors (inventory.view)

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $all = collect($this->getJson('/api/v1/search?q=wegener')->json('groups'))->keyBy('key');
        $this->assertCount(2, $all['pm_jobs']['items']);
        $this->assertSame('Wegener Glass', $all['vendors']['items'][0]['title']);

        // alias / BidEstimate code matches a vendor too
        $this->assertSame('Wegener Glass', collect($this->getJson('/api/v1/search?q=weg%20%24')->json('groups'))->firstWhere('key', 'vendors')['items'][0]['title']);
    }

    public function test_short_queries_and_unauthenticated_requests(): void
    {
        $this->getJson('/api/v1/search?q=ab')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->getJson('/api/v1/search?q=a')->assertOk()->assertJsonPath('groups', []);
        $this->getJson('/api/v1/search?q=zzzzzz-nothing')->assertOk()->assertJsonPath('groups', []);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]))->get('/search')->assertOk()->assertSee('Search');
    }

    public function test_users_without_the_permissions_get_no_hits_in_those_groups(): void
    {
        BusinessJob::withoutGlobalScopes()->create(['job_number' => '7790', 'job_name' => 'Hidden Bid', 'status' => 'bidding']);
        Supplier::create(['name' => 'Hidden Vendor', 'is_active' => true]);
        \App\Models\Role::firstOrCreate(['name' => 'nobody'], ['display_name' => 'Nobody']);
        Sanctum::actingAs(User::factory()->create(['role' => 'nobody', 'is_active' => true]), ['*']);
        $this->getJson('/api/v1/search?q=hidden')->assertOk()->assertJsonPath('groups', []);
    }
}
