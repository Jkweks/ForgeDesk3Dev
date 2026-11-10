<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\FdElevationType;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Models\Shipment;
use App\Models\ShipmentCheckItem;
use App\Models\ShipmentCriterion;
use App\Models\ShipmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShipmentFlowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($u, ['*']);

        return $u;
    }

    private function wo(string $jobNumber = '2001'): FdWorkOrder
    {
        $job = BusinessJob::create(['job_number' => $jobNumber, 'job_name' => 'Job '.$jobNumber, 'status' => 'active', 'site_address' => '1 Main St']);

        return FdWorkOrder::create(['business_job_id' => $job->id, 'release_number' => 1]);
    }

    private function elev(FdWorkOrder $wo, string $tag, ?FdElevationType $type = null): FdWoElevation
    {
        return FdWoElevation::create(['work_order_id' => $wo->id, 'elevation_tag' => $tag, 'quantity' => 2, 'elevation_type_id' => $type?->id]);
    }

    // ---- criteria library ----

    public function test_existing_checklist_items_were_backfilled_into_the_library(): void
    {
        $this->assertTrue(ShipmentCheckItem::query()->exists());
        $this->assertSame(0, ShipmentCheckItem::whereNull('criterion_id')->count());
        $this->assertGreaterThan(0, ShipmentCriterion::count());
    }

    public function test_attaching_a_criterion_and_editing_it_propagates(): void
    {
        $this->admin();
        $type = ShipmentType::create(['name' => 'Louvers']);
        $c = $this->postJson('/api/v1/shipment-criteria', ['label' => 'Blades straight', 'input_type' => 'pass_fail', 'critical' => true])->assertCreated()->json();
        foreach (['pre', 'post'] as $phase) {
            $this->postJson("/api/v1/shipment-types/{$type->id}/items", ['criterion_id' => $c['id'], 'phase' => $phase])->assertCreated();
        }
        $this->postJson("/api/v1/shipment-types/{$type->id}/items", ['criterion_id' => $c['id'], 'phase' => 'pre'])->assertStatus(422);
        $this->assertTrue($type->items()->where('phase', 'pre')->first()->critical);

        $other = ShipmentType::create(['name' => 'Panels']);
        $this->postJson("/api/v1/shipment-types/{$other->id}/items", ['criterion_id' => $c['id'], 'phase' => 'pre', 'critical' => false])->assertCreated();

        $this->putJson("/api/v1/shipment-criteria/{$c['id']}", ['label' => 'Blades straight and clean'])->assertOk();
        $this->assertSame(3, ShipmentCheckItem::where('label', 'Blades straight and clean')->count());
        // the per-category override survived
        $this->assertFalse($other->items()->first()->critical);

        $this->deleteJson("/api/v1/shipment-criteria/{$c['id']}")->assertStatus(422);
    }

    public function test_linked_item_label_is_edited_in_the_library_not_the_category(): void
    {
        $this->admin();
        $type = ShipmentType::create(['name' => 'Louvers']);
        $c = ShipmentCriterion::create(['label' => 'Original', 'input_type' => 'pass_fail'])->fresh();
        $item = $type->items()->create($c->itemDefaults() + ['phase' => 'pre', 'criterion_id' => $c->id]);
        $this->putJson("/api/v1/shipment-check-items/{$item->id}", ['label' => 'Hacked', 'critical' => true])->assertOk();
        $item->refresh();
        $this->assertSame('Original', $item->label);
        $this->assertTrue($item->critical);
    }

    public function test_inline_item_creation_also_joins_the_library(): void
    {
        $this->admin();
        $type = ShipmentType::create(['name' => 'Louvers']);
        $this->postJson("/api/v1/shipment-types/{$type->id}/items", ['phase' => 'pre', 'label' => 'Brand new', 'input_type' => 'text'])->assertCreated();
        $this->assertTrue(ShipmentCriterion::where('label', 'Brand new')->exists());
    }

    // ---- auto draft ----

    public function test_work_order_gets_a_draft_shipment_and_elevations_join_it(): void
    {
        $door = ShipmentType::create(['name' => 'Doors X']);
        $et = FdElevationType::create(['name' => 'Door', 'default_shipment_type_id' => $door->id, 'sort_order' => 1]);
        $wo = $this->wo();

        $s = Shipment::where('work_order_id', $wo->id)->first();
        $this->assertNotNull($s);
        $this->assertSame('draft', $s->status);
        $this->assertSame('1 Main St', $s->destination);
        $this->assertSame([$wo->business_job_id], $s->jobs()->pluck('business_jobs.id')->all());

        $e1 = $this->elev($wo, 'E1', $et);
        $e2 = $this->elev($wo, 'E2');
        $this->assertEqualsCanonicalizing([$e1->id, $e2->id], $s->elevations()->pluck('fd_wo_elevations.id')->all());
        $this->assertSame([$door->id], $s->loads()->pluck('shipment_type_id')->all());
        $this->assertSame(1, Shipment::count());
    }

    public function test_jobs_starting_with_3_get_no_draft_but_can_ship_manually(): void
    {
        $u = $this->admin();
        $wo = $this->wo('3050');
        $e = $this->elev($wo, 'E1');
        $this->assertSame(0, Shipment::count());

        $type = ShipmentType::first();
        $loc = \App\Models\ShipmentLocation::first();
        $id = $this->postJson('/api/v1/shipments', [
            'job_ids' => [$wo->business_job_id], 'loads' => [['shipment_type_id' => $type->id]],
            'pickup_location_id' => $loc?->id, 'dropoff_location_id' => $loc?->id,
        ])->assertCreated()->json('id');

        $r = $this->getJson("/api/v1/shipments/$id/elevations")->assertOk();
        $this->assertSame($e->id, $r->json('work_orders.0.elevations.0.id'));
        $this->putJson("/api/v1/shipments/$id/elevations", ['elevation_ids' => [$e->id]])->assertOk();
        $this->assertSame(1, Shipment::find($id)->elevations()->count());
    }

    // ---- split / move / partial ----

    public function test_split_moves_elevations_to_a_sibling_trip(): void
    {
        $this->admin();
        $type = ShipmentType::first();
        $wo = $this->wo();
        $e1 = $this->elev($wo, 'E1');
        $e2 = $this->elev($wo, 'E2');
        $e3 = $this->elev($wo, 'E3');
        $s = Shipment::where('work_order_id', $wo->id)->first();
        $s->loads()->create(['shipment_type_id' => $type->id]);

        $new = $this->postJson("/api/v1/shipments/{$s->id}/split", ['elevation_ids' => [$e2->id, $e3->id]])->assertCreated()->json();
        $n = Shipment::find($new['id']);
        $this->assertSame($wo->id, $n->work_order_id);
        $this->assertSame('draft', $n->status);
        $this->assertSame([$type->id], $n->loads()->pluck('shipment_type_id')->all());
        $this->assertSame([$e1->id], $s->elevations()->pluck('fd_wo_elevations.id')->all());
        $this->assertEqualsCanonicalizing([$e2->id, $e3->id], $n->elevations()->pluck('fd_wo_elevations.id')->all());

        $this->assertSame('Trip 1 of 2', $this->getJson("/api/v1/shipments/{$s->id}")->json('trip'));
        $this->assertSame('Trip 2 of 2', $this->getJson("/api/v1/shipments/{$n->id}")->json('trip'));

        // picking an elevation from the sibling draft moves it back
        $this->putJson("/api/v1/shipments/{$s->id}/elevations", ['elevation_ids' => [$e1->id, $e2->id]])->assertOk();
        $this->assertSame([$e3->id], $n->elevations()->pluck('fd_wo_elevations.id')->all());

        // can't move what isn't on the source
        $this->postJson("/api/v1/shipments/{$s->id}/split", ['elevation_ids' => [$e3->id]])->assertStatus(422);
    }

    public function test_shipped_shipments_cannot_change_elevations_or_split(): void
    {
        $this->admin();
        $wo = $this->wo();
        $e = $this->elev($wo, 'E1');
        $s = Shipment::where('work_order_id', $wo->id)->first();
        $s->update(['status' => 'shipped']);
        $this->putJson("/api/v1/shipments/{$s->id}/elevations", ['elevation_ids' => []])->assertStatus(422);
        $this->postJson("/api/v1/shipments/{$s->id}/split", [])->assertStatus(422);

        $other = Shipment::create(['shipment_number' => Shipment::nextNumber(), 'status' => 'draft', 'work_order_id' => $wo->id]);
        $other->jobs()->sync([$wo->business_job_id]);
        $this->putJson("/api/v1/shipments/{$other->id}/elevations", ['elevation_ids' => [$e->id]])->assertStatus(422);
    }

    public function test_partial_delivery_keeps_the_elevation_selectable_for_a_later_trip(): void
    {
        $this->admin();
        $wo = $this->wo();
        $e1 = $this->elev($wo, 'E1');
        $e2 = $this->elev($wo, 'E2');
        $s = Shipment::where('work_order_id', $wo->id)->first();

        $this->patchJson("/api/v1/shipments/{$s->id}/elevations/{$e1->id}", ['partial' => true])->assertStatus(422); // note required
        $this->patchJson("/api/v1/shipments/{$s->id}/elevations/{$e1->id}", ['partial' => true, 'partial_note' => 'Glass only; frames next week'])->assertOk();

        $s->update(['status' => 'delivered']);
        $svc = app(\App\Services\Shipping\WorkOrderShipmentService::class);
        $st = $svc->states([$e1->id, $e2->id]);
        $this->assertSame('partial', $st[$e1->id]['state']);
        $this->assertSame('delivered', $st[$e2->id]['state']);

        $this->assertEqualsCanonicalizing([$e1->id], collect($this->getJson('/api/v1/shipments/unscheduled')->json())->flatMap(fn ($w) => collect($w['elevations'])->pluck('id'))->all());

        // a new trip can take it again; the delivered one cannot be re-added
        $n = Shipment::create(['shipment_number' => Shipment::nextNumber(), 'status' => 'draft', 'work_order_id' => $wo->id]);
        $n->jobs()->sync([$wo->business_job_id]);
        $this->putJson("/api/v1/shipments/{$n->id}/elevations", ['elevation_ids' => [$e2->id]])->assertStatus(422);
        $this->putJson("/api/v1/shipments/{$n->id}/elevations", ['elevation_ids' => [$e1->id]])->assertOk();
        $this->assertSame('scheduled', $svc->states([$e1->id])[$e1->id]['state']);
        $this->assertTrue($svc->states([$e1->id])[$e1->id]['partial_history']);
    }

    public function test_cancelled_shipments_free_their_elevations(): void
    {
        $wo = $this->wo();
        $e = $this->elev($wo, 'E1');
        $s = Shipment::where('work_order_id', $wo->id)->first();
        $svc = app(\App\Services\Shipping\WorkOrderShipmentService::class);
        $this->assertSame('scheduled', $svc->states([$e->id])[$e->id]['state']);
        $s->update(['status' => 'cancelled']);
        $this->assertSame('unscheduled', $svc->states([$e->id])[$e->id]['state']);
    }

    public function test_elevation_type_default_category_is_saved(): void
    {
        $this->admin();
        $type = ShipmentType::first();
        $id = $this->postJson('/api/v1/elevation-types', ['name' => 'Louver Wall', 'default_shipment_type_id' => $type->id])->assertCreated()->json('id');
        $this->assertSame($type->id, FdElevationType::find($id)->default_shipment_type_id);
        $this->putJson("/api/v1/elevation-types/$id", ['default_shipment_type_id' => null])->assertOk();
        $this->assertNull(FdElevationType::find($id)->default_shipment_type_id);
    }

    public function test_index_and_detail_carry_elevation_counts(): void
    {
        $this->admin();
        $wo = $this->wo();
        $this->elev($wo, 'E1');
        $row = collect($this->getJson('/api/v1/shipments')->assertOk()->json())->first();
        $this->assertSame(1, $row['elevations_count']);
    }
}
