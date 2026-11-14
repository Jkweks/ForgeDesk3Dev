<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** PM Suite vendor parity: contacts, aliases, BidEstimate code (docs/plans/pm-suite-integration.md). */
class SupplierPmSuiteParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
    }

    public function test_create_and_update_sync_contacts_phones_and_aliases(): void
    {
        $id = $this->postJson('/api/v1/suppliers', [
            'name' => 'Perry Glass',
            'bidestimate_code' => 'Perrybrg $',
            'products_services' => 'Glass',
            'extra_phones' => [['label' => 'Shop', 'number' => '555-0100']],
            'contacts' => [
                ['name' => 'Ann', 'email' => 'ann@example.com', 'is_preferred_rep' => true],
                ['name' => 'Bob'],
            ],
            'aliases' => ['Perry Brg', 'PERRYBURG'],
        ])->assertCreated()->json('supplier.id');

        $s = Supplier::with(['contacts', 'aliases'])->findOrFail($id);
        $this->assertSame(['Ann', 'Bob'], $s->contacts->pluck('name')->all());
        $this->assertTrue($s->contacts->first()->is_preferred_rep);
        $this->assertSame('555-0100', $s->phones()->first()->number);
        $this->assertSame('Shop', $s->phones()->first()->label);
        $this->assertCount(2, $s->aliases);

        $this->putJson("/api/v1/suppliers/{$id}", ['name' => 'Perry Glass', 'contacts' => [['name' => 'Cy']], 'aliases' => ['Perry Brg']])->assertOk();
        $s->refresh()->load(['contacts', 'aliases']);
        $this->assertSame(['Cy'], $s->contacts->pluck('name')->all());
        $this->assertCount(1, $s->aliases);

        // Omitting contacts/aliases leaves them untouched
        $this->putJson("/api/v1/suppliers/{$id}", ['name' => 'Perry Glass 2'])->assertOk();
        $this->assertCount(1, $s->fresh()->contacts);
    }

    public function test_alias_cannot_belong_to_two_vendors(): void
    {
        $this->postJson('/api/v1/suppliers', ['name' => 'A', 'aliases' => ['Shared Name']])->assertCreated();
        $this->postJson('/api/v1/suppliers', ['name' => 'B', 'aliases' => ['shared  name!']])->assertStatus(422);
    }

    public function test_find_by_estimate_tag_matches_code_alias_and_name(): void
    {
        $s = Supplier::create(['name' => 'Perry Glass', 'bidestimate_code' => 'Perrybrg $', 'is_active' => true]);
        $s->syncAliases(['Perry Brg']);

        $this->assertSame($s->id, Supplier::findByEstimateTag('perrybrg')->id);
        $this->assertSame($s->id, Supplier::findByEstimateTag('PERRY  BRG')->id);
        $this->assertSame($s->id, Supplier::findByEstimateTag('perry glass')->id);
        $this->assertNull(Supplier::findByEstimateTag('unknown'));
        $this->assertNull(Supplier::findByEstimateTag('  '));
    }
}
