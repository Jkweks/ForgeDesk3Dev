<?php

namespace Tests\Feature;

use App\Models\FieldAsset;
use App\Models\FieldAssetCompliance;
use App\Models\FieldAssetMaintPlan;
use App\Models\FieldAssetType;
use App\Models\FieldAssetWorkOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FleetAssetsTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): User
    {
        $u = User::factory()->create(['role' => $role, 'is_active' => true]);
        Sanctum::actingAs($u, ['*']);

        return $u;
    }

    private function asset(array $attrs = []): FieldAsset
    {
        $type = FieldAssetType::where('name', 'Truck')->first();

        // current_meter is deliberately not mass-assignable (only FleetService::recordMeter moves it).
        $meter = $attrs['current_meter'] ?? null;
        unset($attrs['current_meter']);
        $asset = FieldAsset::create($attrs + [
            'asset_number' => FieldAsset::nextAssetNumber(), 'name' => 'F-250', 'field_asset_type_id' => $type->id,
        ]);
        if ($meter !== null) {
            $asset->forceFill(['current_meter' => $meter])->save();
        }

        return $asset;
    }

    public function test_seeded_types_and_permissions_exist(): void
    {
        $this->assertTrue(FieldAssetType::where('name', 'Swing Stage')->exists());
        $this->assertNotEmpty(FieldAssetType::where('name', 'Truck')->first()->default_checklist);
        $this->assertTrue(\App\Models\Permission::where('name', 'fleet.view')->exists());
    }

    public function test_office_staff_can_view_but_not_manage_or_see_costs(): void
    {
        $asset = $this->asset(['purchase_cost' => 42000]);
        $this->as('office_staff');

        $this->getJson('/api/v1/field-assets')->assertOk();
        $this->getJson("/api/v1/field-assets/{$asset->id}")->assertOk()->assertJsonMissingPath('purchase_cost');
        $this->postJson('/api/v1/field-assets', ['name' => 'x', 'field_asset_type_id' => $asset->field_asset_type_id])->assertForbidden();
        $this->deleteJson("/api/v1/field-assets/{$asset->id}")->assertForbidden();
        $this->getJson('/api/v1/fleet/inbox')->assertForbidden();
    }

    public function test_manager_sees_costs_and_cannot_delete(): void
    {
        $asset = $this->asset(['purchase_cost' => 42000]);
        $this->as('manager');

        $this->getJson("/api/v1/field-assets/{$asset->id}")->assertOk()->assertJsonPath('purchase_cost', '42000.00');
        $this->deleteJson("/api/v1/field-assets/{$asset->id}")->assertForbidden();
    }

    public function test_create_asset_generates_number_and_records_initial_meter(): void
    {
        $this->as('admin');
        $typeId = FieldAssetType::where('name', 'Truck')->value('id');

        $res = $this->postJson('/api/v1/field-assets', ['name' => 'Ram 2500', 'field_asset_type_id' => $typeId, 'current_meter' => 12000])->assertCreated();

        $this->assertSame('FA-0001', $res->json('asset_number'));
        $this->assertEquals(12000, FieldAsset::first()->current_meter);
        $this->assertSame(1, FieldAsset::first()->meterReadings()->count());
    }

    public function test_meter_cannot_go_backwards_without_correction_flag(): void
    {
        $this->as('admin');
        $asset = $this->asset();
        $this->postJson("/api/v1/field-assets/{$asset->id}/meter", ['reading' => 5000])->assertOk();

        $this->postJson("/api/v1/field-assets/{$asset->id}/meter", ['reading' => 4000])->assertStatus(422);
        $this->assertEquals(5000, $asset->fresh()->current_meter);

        $this->postJson("/api/v1/field-assets/{$asset->id}/meter", ['reading' => 4000, 'is_correction' => true])->assertOk();
        $this->assertEquals(4000, $asset->fresh()->current_meter);
    }

    public function test_plan_requires_an_interval_and_computes_next_due(): void
    {
        $this->as('admin');
        $asset = $this->asset(['current_meter' => 1000]);

        $this->postJson("/api/v1/field-assets/{$asset->id}/plans", ['title' => 'Oil'])->assertStatus(422);

        $res = $this->postJson("/api/v1/field-assets/{$asset->id}/plans", [
            'title' => 'Oil change', 'interval_days' => 180, 'interval_meter' => 5000, 'starts_on' => '2026-01-01',
        ])->assertCreated();

        $this->assertSame('2026-06-30', $res->json('next_due_at'));
        $this->assertEquals(6000, $res->json('next_due_meter'));
    }

    public function test_plan_due_state_is_whichever_comes_first(): void
    {
        $asset = $this->asset(['current_meter' => 5900]);
        $plan = FieldAssetMaintPlan::create([
            'field_asset_id' => $asset->id, 'title' => 'Oil', 'interval_days' => 365, 'interval_meter' => 5000,
            'warn_days' => 14, 'warn_meter' => 250, 'starts_on' => now()->toDateString(), 'starts_meter' => 1000,
        ]);
        app(\App\Services\Fleet\FleetService::class)->recalcPlan($plan);

        $this->assertSame('due_soon', $plan->fresh('asset')->due_state);   // meter within 250 of 6000, date far away
        $asset->forceFill(['current_meter' => 6000])->save();
        $this->assertSame('overdue', $plan->fresh('asset')->due_state);    // meter reached

        $date = FieldAssetMaintPlan::create([
            'field_asset_id' => $asset->id, 'title' => 'Cert', 'interval_days' => 30, 'warn_days' => 14,
            'starts_on' => now()->subDays(31)->toDateString(),
        ]);
        app(\App\Services\Fleet\FleetService::class)->recalcPlan($date);
        $this->assertSame('overdue', $date->fresh('asset')->due_state);
    }

    public function test_completing_a_work_order_advances_the_plan_and_meter(): void
    {
        $this->as('admin');
        $asset = $this->asset(['current_meter' => 6100]);
        $plan = FieldAssetMaintPlan::create([
            'field_asset_id' => $asset->id, 'title' => 'Oil', 'interval_days' => 180, 'interval_meter' => 5000,
            'starts_on' => '2026-01-01', 'starts_meter' => 1000,
        ]);
        app(\App\Services\Fleet\FleetService::class)->recalcPlan($plan);

        $wo = $this->postJson('/api/v1/field-asset-work-orders', [
            'field_asset_id' => $asset->id, 'plan_id' => $plan->id, 'type' => 'preventive', 'title' => 'Oil change',
        ])->assertCreated()->json();

        $this->postJson("/api/v1/field-asset-work-orders/{$wo['id']}/complete", [
            'completed_on' => '2026-10-20', 'meter_at_service' => 6200, 'labor_cost' => 50, 'parts_cost' => 70.5, 'outside_cost' => 0,
        ])->assertOk()->assertJsonPath('status', 'completed')->assertJsonPath('total_cost', '120.50');

        $plan->refresh();
        $this->assertSame('2026-10-20', $plan->last_done_at->format('Y-m-d'));
        $this->assertSame('2027-04-18', $plan->next_due_at->format('Y-m-d'));
        $this->assertEquals(11200, $plan->next_due_meter);
        $this->assertEquals(6200, $asset->fresh()->current_meter);
        $this->postJson("/api/v1/field-asset-work-orders/{$wo['id']}/complete", [])->assertStatus(422);
    }

    public function test_service_meter_below_current_does_not_roll_back_the_asset_meter(): void
    {
        $this->as('admin');
        $asset = $this->asset(['current_meter' => 9000]);
        $wo = FieldAssetWorkOrder::create(['wo_number' => 'FWO-00001', 'field_asset_id' => $asset->id, 'title' => 'Old repair']);

        $this->postJson("/api/v1/field-asset-work-orders/{$wo->id}/complete", ['meter_at_service' => 8000])->assertOk();

        $this->assertEquals(9000, $asset->fresh()->current_meter);
        $this->assertEquals(8000, $wo->fresh()->meter_at_service);
    }

    public function test_costs_are_stripped_for_users_without_cost_permission(): void
    {
        $asset = $this->asset();
        $this->as('office_staff'); // has no fleet.manage either, so test the helper path through a manager-less grant
        \App\Models\Role::where('name', 'office_staff')->first()->assignPermission('fleet.manage');

        $res = $this->postJson('/api/v1/field-asset-work-orders', [
            'field_asset_id' => $asset->id, 'title' => 'Fix', 'labor_cost' => 500,
        ])->assertCreated();

        $this->assertNull(FieldAssetWorkOrder::find($res->json('id'))->labor_cost);
    }

    public function test_failed_critical_inspection_opens_defect_work_order_and_can_mark_out_of_service(): void
    {
        $this->as('admin');
        $asset = $this->asset();

        $res = $this->postJson("/api/v1/field-assets/{$asset->id}/inspections", [
            'template_name' => 'Pre-trip', 'mark_out_of_service' => true,
            'items' => [
                ['label' => 'Brakes', 'critical' => true, 'result' => 'fail', 'comment' => 'Soft pedal'],
                ['label' => 'Horn', 'result' => 'pass'],
            ],
        ])->assertCreated();

        $this->assertSame('fail', $res->json('inspection.result'));
        $this->assertSame('inspection_defect', $res->json('work_order.type'));
        $this->assertSame('critical', $res->json('work_order.priority'));
        $this->assertSame('out_of_service', $asset->fresh()->status);
    }

    public function test_minor_failure_is_pass_with_issues_and_does_not_take_asset_out_of_service(): void
    {
        $this->as('admin');
        $asset = $this->asset();

        $res = $this->postJson("/api/v1/field-assets/{$asset->id}/inspections", [
            'items' => [['label' => 'Mirrors', 'result' => 'fail'], ['label' => 'Brakes', 'critical' => true, 'result' => 'pass']],
        ])->assertCreated();

        $this->assertSame('pass_with_issues', $res->json('inspection.result'));
        $this->assertSame('active', $asset->fresh()->status);
    }

    public function test_documents_are_stored_privately_and_download_requires_view_permission(): void
    {
        Storage::fake('local');
        $this->as('admin');
        $asset = $this->asset();

        $res = $this->postJson("/api/v1/field-assets/{$asset->id}/documents", [
            'doc_type' => 'insurance', 'expires_on' => '2027-01-01',
            'files' => [UploadedFile::fake()->create('policy.pdf', 100, 'application/pdf')],
        ])->assertCreated();

        $doc = \App\Models\FieldAssetDocument::first();
        Storage::disk('local')->assertExists($doc->file_path);
        $this->assertArrayNotHasKey('file_path', $res->json(0));
        $this->getJson("/api/v1/field-assets/{$asset->id}/documents/{$doc->id}/download")->assertOk();

        // soft delete keeps the file
        $this->deleteJson("/api/v1/field-assets/{$asset->id}/documents/{$doc->id}")->assertOk();
        Storage::disk('local')->assertExists($doc->file_path);
    }

    public function test_executable_uploads_are_rejected(): void
    {
        Storage::fake('local');
        $this->as('admin');
        $asset = $this->asset();

        $this->postJson("/api/v1/field-assets/{$asset->id}/documents", [
            'doc_type' => 'other', 'files' => [UploadedFile::fake()->create('x.php', 1, 'text/x-php')],
        ])->assertStatus(422);
    }

    public function test_document_cannot_attach_to_another_assets_work_order(): void
    {
        Storage::fake('local');
        $this->as('admin');
        $a = $this->asset();
        $b = $this->asset(['name' => 'Other']);
        $wo = FieldAssetWorkOrder::create(['wo_number' => 'FWO-00001', 'field_asset_id' => $b->id, 'title' => 'x']);

        $this->postJson("/api/v1/field-assets/{$a->id}/documents", [
            'doc_type' => 'invoice', 'work_order_id' => $wo->id,
            'files' => [UploadedFile::fake()->create('i.pdf', 10, 'application/pdf')],
        ])->assertStatus(422);
    }

    public function test_check_due_raises_alerts_once(): void
    {
        $asset = $this->asset();
        FieldAssetCompliance::create(['field_asset_id' => $asset->id, 'kind' => 'registration', 'expires_on' => now()->addDays(20)->toDateString()]);
        FieldAssetCompliance::create(['field_asset_id' => $asset->id, 'kind' => 'insurance', 'expires_on' => now()->subDay()->toDateString()]);
        $plan = FieldAssetMaintPlan::create(['field_asset_id' => $asset->id, 'title' => 'Brake check', 'interval_days' => 30, 'starts_on' => now()->subDays(60)->toDateString()]);
        app(\App\Services\Fleet\FleetService::class)->recalcPlan($plan);

        $this->artisan('fleet:check-due')->assertSuccessful();
        $first = \App\Models\SystemNotification::count();
        $this->assertSame(3, $first);

        $this->artisan('fleet:check-due')->assertSuccessful();
        $this->assertSame($first, \App\Models\SystemNotification::count());
    }

    public function test_superseded_compliance_record_does_not_alert(): void
    {
        $asset = $this->asset();
        FieldAssetCompliance::create(['field_asset_id' => $asset->id, 'kind' => 'registration', 'expires_on' => now()->subMonth()->toDateString()]);
        FieldAssetCompliance::create(['field_asset_id' => $asset->id, 'kind' => 'registration', 'expires_on' => now()->addYear()->toDateString()]);

        $this->assertSame([], app(\App\Services\Fleet\FleetService::class)->upcoming(90)['compliance']);
    }

    public function test_retired_assets_drop_out_of_due_lists_and_default_listing(): void
    {
        $this->as('admin');
        $asset = $this->asset(['status' => 'retired']);
        FieldAssetCompliance::create(['field_asset_id' => $asset->id, 'kind' => 'insurance', 'expires_on' => now()->subDay()->toDateString()]);

        $this->assertSame([], app(\App\Services\Fleet\FleetService::class)->upcoming(90)['compliance']);
        $this->assertCount(0, $this->getJson('/api/v1/field-assets')->json('data'));
        $this->assertCount(1, $this->getJson('/api/v1/field-assets?include_inactive=1')->json('data'));
    }

    public function test_dashboard_and_search_work(): void
    {
        $this->as('admin');
        $this->asset(['name' => 'Swing Stage Alpha', 'license_plate' => 'ABC123']);

        $this->getJson('/api/v1/fleet/dashboard')->assertOk()->assertJsonPath('total_assets', 1)->assertJsonStructure(['sync', 'cost_12_months']);
        $this->assertCount(1, $this->getJson('/api/v1/field-assets?search=abc1')->json('data'));
        $this->assertCount(0, $this->getJson('/api/v1/field-assets?search=zzz')->json('data'));
    }

    public function test_pages_render(): void
    {
        $this->as('admin');
        $asset = $this->asset();
        $this->get('/fleet')->assertOk();
        $this->get("/fleet/assets/{$asset->id}")->assertOk();
    }
}
