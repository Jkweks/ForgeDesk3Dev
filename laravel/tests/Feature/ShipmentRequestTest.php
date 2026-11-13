<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ShipmentRequest;
use App\Models\ShipmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShipmentRequestTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): User
    {
        foreach (['shipments.view', 'shipments.request', 'shipments.approve'] as $p) {
            Permission::firstOrCreate(['name' => $p], ['display_name' => $p, 'description' => $p, 'category' => 'shipments']);
        }
        $r = Role::firstOrCreate(['name' => $role], ['display_name' => $role]);
        $r->assignPermission('shipments.view');
        $r->assignPermission('shipments.request');
        if (in_array($role, ['admin', 'manager'], true)) {
            $r->assignPermission('shipments.approve');
        }
        $u = User::factory()->create(['role' => $role, 'is_active' => true]);
        Sanctum::actingAs($u, ['*']);

        return $u;
    }

    private function payload(): array
    {
        $job = BusinessJob::create(['job_number' => '2001', 'job_name' => 'Job', 'status' => 'active']);
        $type = ShipmentType::firstOrCreate(['name' => 'Metal'], ['is_active' => true, 'sort_order' => 1]);

        return ['requested_date' => '2026-11-20', 'job_id' => $job->id, 'destination' => 'Site', 'shipment_type_ids' => [$type->id], 'notes' => 'hi'];
    }

    public function test_staff_can_request_but_not_approve_and_manager_approval_creates_draft(): void
    {
        $this->as('office_staff');
        $p = $this->payload();
        $id = $this->postJson('/api/v1/shipment-requests', $p)->assertCreated()->json('id');
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(0);
        $this->postJson("/api/v1/shipment-requests/$id/approve")->assertForbidden();

        $this->as('manager');
        $n = $this->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(1)->json('0');
        $this->assertSame('shipment_request', $n['type']);
        $res = $this->postJson("/api/v1/shipment-requests/$id/approve")->assertOk();
        $req = ShipmentRequest::find($id);
        $this->assertSame('approved', $req->status);
        $s = $req->shipment;
        $this->assertSame('2026-11-20', $s->ship_date->toDateString());
        $this->assertSame('draft', $s->status);
        $this->assertSame([$p['job_id']], $s->jobs()->pluck('business_jobs.id')->all());
        $this->assertCount(1, $s->loads);
        $this->postJson("/api/v1/shipment-requests/$id/approve")->assertStatus(422);
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(0);
    }

    public function test_reject_and_validation(): void
    {
        $this->as('admin');
        $this->postJson('/api/v1/shipment-requests', ['requested_date' => '2026-11-20'])->assertStatus(422);
        $id = $this->postJson('/api/v1/shipment-requests', ['requested_date' => '2026-11-20', 'custom_job' => 'Walk-in'])->assertCreated()->json('id');
        $this->postJson("/api/v1/shipment-requests/$id/reject", ['decision_note' => 'no'])->assertOk();
        $this->assertSame('rejected', ShipmentRequest::find($id)->status);
        $this->getJson('/api/v1/shipment-requests')->assertOk()->assertJsonCount(0);
    }
}
