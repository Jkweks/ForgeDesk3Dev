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

    // ---- previously delivered + partial carry-over ----

    public function test_previously_delivered_elevations_leave_open_shipments_and_read_as_delivered(): void
    {
        $this->admin();
        $wo = $this->wo();
        $e1 = $this->elev($wo, 'E1');
        $e2 = $this->elev($wo, 'E2');
        $s = Shipment::where('work_order_id', $wo->id)->first();
        $svc = app(\App\Services\Shipping\WorkOrderShipmentService::class);

        $this->postJson('/api/v1/shipments/elevations/previously-delivered', [
            'elevation_ids' => [$e1->id], 'delivered' => true, 'delivered_on' => '2026-10-01', 'note' => 'Hand delivered',
        ])->assertOk();

        $this->assertSame([$e2->id], $s->elevations()->pluck('fd_wo_elevations.id')->all());
        $st = $svc->states([$e1->id])[$e1->id];
        $this->assertSame('delivered', $st['state']);
        $this->assertSame('Hand delivered', $st['previously_delivered']['note']);
        $this->assertSame([], collect($this->getJson('/api/v1/shipments/unscheduled')->json())->flatMap(fn ($w) => collect($w['elevations'])->pluck('id'))->diff([$e2->id])->all());

        // cannot be put back on a shipment while marked
        $this->putJson("/api/v1/shipments/{$s->id}/elevations", ['elevation_ids' => [$e1->id, $e2->id]])->assertStatus(422);

        // un-marking makes it schedulable again
        $this->postJson('/api/v1/shipments/elevations/previously-delivered', ['elevation_ids' => [$e1->id], 'delivered' => false])->assertOk();
        $this->assertSame('unscheduled', $svc->states([$e1->id])[$e1->id]['state']);
    }

    public function test_cannot_mark_an_elevation_on_a_truck_as_previously_delivered(): void
    {
        $this->admin();
        $wo = $this->wo();
        $e = $this->elev($wo, 'E1');
        Shipment::where('work_order_id', $wo->id)->first()->update(['status' => 'shipped']);

        $this->postJson('/api/v1/shipments/elevations/previously-delivered', ['elevation_ids' => [$e->id], 'delivered' => true])->assertStatus(422);
    }

    public function test_partial_elevations_carry_over_to_the_next_open_shipment_when_received(): void
    {
        $this->admin();
        $wo = $this->wo();
        $e1 = $this->elev($wo, 'E1');
        $e2 = $this->elev($wo, 'E2');
        $s = Shipment::where('work_order_id', $wo->id)->first();
        $svc = app(\App\Services\Shipping\WorkOrderShipmentService::class);

        $this->patchJson("/api/v1/shipments/{$s->id}/elevations/{$e1->id}", ['partial' => true, 'partial_note' => 'Glass only'])->assertOk();
        $this->assertNull($svc->carryOverPartials($s), 'not received yet: nothing owed');

        $s->update(['status' => 'awaiting_verification']);
        $target = $svc->carryOverPartials($s->fresh());
        $this->assertNotNull($target);
        $this->assertNotSame($s->id, $target->id);
        $this->assertSame('draft', $target->status);
        $this->assertSame($wo->id, $target->work_order_id);
        $this->assertSame([$e1->id], $target->elevations()->pluck('fd_wo_elevations.id')->all());
        $this->assertFalse((bool) $target->elevations()->first()->pivot->partial);
        $this->assertSame('scheduled', $svc->states([$e1->id])[$e1->id]['state']);

        // idempotent
        $this->assertNull($svc->carryOverPartials($s->fresh()));
        $this->assertSame(1, $target->elevations()->count());

        // the next trip's detail tells the loader what is still owed
        $d = $this->getJson("/api/v1/shipments/{$target->id}")->assertOk()->json('elevations.0.outstanding');
        $this->assertSame('Glass only', $d[0]['note']);
        $this->assertSame($s->id, $d[0]['id']);
        $this->assertArrayHasKey('date', $d[0]);
        $picker = collect($this->getJson("/api/v1/shipments/{$target->id}/elevations")->json('work_orders.0.elevations'))->firstWhere('id', $e1->id);
        $this->assertSame([$s->id], collect($picker['outstanding'])->pluck('id')->all());

        // a second partial goes onto the same open draft rather than a third trip
        $this->patchJson("/api/v1/shipments/{$s->id}/elevations/{$e2->id}", ['partial' => true, 'partial_note' => 'Frame only'])->assertOk();
        // (flagging on an already-received shipment carries it over straight away)
        $this->assertSame(2, $target->elevations()->count());
        $this->assertSame(2, Shipment::where('work_order_id', $wo->id)->count(), 'no third trip was opened');
    }

    public function test_partial_on_a_shipped_truck_can_be_added_to_another_shipment(): void
    {
        $this->admin();
        $wo = $this->wo();
        $e = $this->elev($wo, 'E1');
        $s = Shipment::where('work_order_id', $wo->id)->first();
        $svc = app(\App\Services\Shipping\WorkOrderShipmentService::class);
        $n = Shipment::create(['shipment_number' => Shipment::nextNumber(), 'status' => 'draft', 'work_order_id' => $wo->id]);
        $n->jobs()->sync([$wo->business_job_id]);

        // Full load on a truck: blocked.
        $s->update(['status' => 'shipped']);
        $this->putJson("/api/v1/shipments/{$n->id}/elevations", ['elevation_ids' => [$e->id]])->assertStatus(422);
        $this->assertSame('in_transit', $svc->states([$e->id])[$e->id]['state']);

        // Tagged partial while on the road: the rest is still owed and can ride another shipment.
        $s->elevations()->updateExistingPivot($e->id, ['partial' => true, 'partial_note' => 'Glass only']);
        $this->assertSame('partial', $svc->states([$e->id])[$e->id]['state']);
        $this->putJson("/api/v1/shipments/{$n->id}/elevations", ['elevation_ids' => [$e->id]])->assertOk();
        $this->assertSame('scheduled', $svc->states([$e->id])[$e->id]['state']);
    }

    // ---- picked up by others ----

    public function test_pickup_by_others_needs_no_loads_locations_or_inspection_and_can_be_marked_picked_up(): void
    {
        $this->admin();
        $wo = $this->wo();
        $e1 = $this->elev($wo, 'E1');
        $e2 = $this->elev($wo, 'E2');
        $svc = app(\App\Services\Shipping\WorkOrderShipmentService::class);

        // No categories, no pickup/drop-off legs.
        $id = $this->postJson('/api/v1/shipments', ['pickup_by_others' => true, 'job_ids' => [$wo->business_job_id], 'picked_up_by' => 'ACME Glazing'])
            ->assertCreated()->json('id');
        $s = Shipment::find($id);
        $this->assertTrue($s->pickup_by_others);
        $this->assertSame('draft', $s->status);
        $this->assertSame(0, $s->loads()->count());

        // A normal shipment still needs them.
        $this->postJson('/api/v1/shipments', ['job_ids' => [$wo->business_job_id]])->assertStatus(422);

        // No inspections, no shipping.
        $this->postJson("/api/v1/shipments/$id/pre-inspection", ['answers' => '{}'])->assertStatus(422);
        $this->postJson("/api/v1/shipments/$id/ship")->assertStatus(422);

        // Elevations ride along; E2 is tagged partial.
        $this->putJson("/api/v1/shipments/$id/elevations", ['elevation_ids' => [$e1->id, $e2->id]])->assertOk();
        $this->patchJson("/api/v1/shipments/$id/elevations/{$e2->id}", ['partial' => true, 'partial_note' => 'Frame only'])->assertOk();

        $this->postJson("/api/v1/shipments/$id/picked-up", ['note' => 'x'])->assertStatus(422); // who?
        $r = $this->postJson("/api/v1/shipments/$id/picked-up", ['picked_up_by' => 'ACME Glazing', 'picked_up_at' => '2026-11-18', 'note' => 'Signed at the dock'])->assertOk();
        $this->assertSame('picked_up', $r->json('status'));
        $this->assertSame('ACME Glazing', $r->json('picked_up_by'));
        $s->refresh();
        $this->assertSame('2026-11-18', $s->ship_date->toDateString());
        $this->assertStringContainsString('Signed at the dock', (string) $s->notes);

        $st = $svc->states([$e1->id, $e2->id]);
        $this->assertSame('delivered', $st[$e1->id]['state']);
        $this->assertSame('scheduled', $st[$e2->id]['state'], 'partial pickup carried over to a new trip');

        // Closed: cannot be cancelled or deleted, but can be undone.
        $this->postJson("/api/v1/shipments/$id/cancel", ['reason' => 'oops'])->assertStatus(422);
        $this->deleteJson("/api/v1/shipments/$id")->assertStatus(422);
        $this->postJson("/api/v1/shipments/$id/reopen-pickup")->assertOk()->assertJsonPath('status', 'draft');
        $this->assertNull($s->fresh()->picked_up_at);
    }

    public function test_pickup_shipments_accept_photos_before_and_after_pickup(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->admin();
        $wo = $this->wo();
        $id = $this->postJson('/api/v1/shipments', ['pickup_by_others' => true, 'job_ids' => [$wo->business_job_id]])->assertCreated()->json('id');
        $photo = fn () => \Illuminate\Http\UploadedFile::fake()->image('load.jpg', 40, 40);

        $this->post("/api/v1/shipments/$id/documents", ['files' => [$photo()]], ['Accept' => 'application/json'])->assertCreated();
        $this->postJson("/api/v1/shipments/$id/picked-up", ['picked_up_by' => 'Rita'])->assertOk();
        $r = $this->post("/api/v1/shipments/$id/documents", ['files' => [$photo()]], ['Accept' => 'application/json'])->assertCreated();
        $this->assertCount(2, $r->json('documents'));
        $this->assertSame('pickup_photo', $r->json('documents.0.kind'));

        // A normal draft shipment still refuses tickets until it has shipped.
        $other = $this->wo('2002');
        $nid = Shipment::where('work_order_id', $other->id)->value('id');
        $this->post("/api/v1/shipments/$nid/documents", ['files' => [$photo()]], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_work_order_shipments_tab_lists_shipments_and_elevation_states(): void
    {
        $this->admin();
        $wo = $this->wo();
        $e1 = $this->elev($wo, 'E1');
        $e2 = $this->elev($wo, 'E2');
        $e3 = $this->elev($wo, 'E3');
        $s = Shipment::where('work_order_id', $wo->id)->first();
        $this->postJson('/api/v1/shipments/elevations/previously-delivered', ['elevation_ids' => [$e3->id], 'delivered' => true, 'note' => 'Hand delivered'])->assertOk();
        $this->patchJson("/api/v1/shipments/{$s->id}/elevations/{$e1->id}", ['partial' => true, 'partial_note' => 'Glass only'])->assertOk();
        $s->update(['status' => 'awaiting_verification']);
        app(\App\Services\Shipping\WorkOrderShipmentService::class)->carryOverPartials($s->fresh());

        $r = $this->getJson("/api/v1/work-orders/{$wo->id}/shipments")->assertOk();
        $this->assertCount(2, $r->json('shipments'));
        $this->assertContains('Trip 1 of 2', collect($r->json('shipments'))->pluck('trip')->all());
        $els = collect($r->json('elevations'))->keyBy('id');
        $this->assertSame('scheduled', $els[$e1->id]['state']);
        $this->assertSame('Glass only', $els[$e1->id]['outstanding'][0]['note']);
        $this->assertSame($s->id, $els[$e1->id]['outstanding'][0]['id']);
        $this->assertSame('delivered', $els[$e2->id]['state']);
        $this->assertSame('Hand delivered', $els[$e3->id]['previously_delivered']['note']);
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
