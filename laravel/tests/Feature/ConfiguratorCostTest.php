<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\DoorFrameConfiguration;
use App\Models\DoorFrameHardwarePart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfiguratorCostTest extends TestCase
{
    use RefreshDatabase;

    public function test_cost_prices_bom_quantities_at_net_price_and_flags_missing(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $job = BusinessJob::create(['job_number' => 'C-1', 'job_name' => 'Cost job', 'status' => 'active']);
        $config = DoorFrameConfiguration::create(['business_job_id' => $job->id, 'job_scope' => 'door_and_frame', 'status' => 'draft', 'quantity' => 1]);

        $sup = \App\Models\Supplier::create(['name' => 'S']);
        $priced = Product::create(['sku' => 'COST-A', 'description' => 'Priced', 'supplier_id' => $sup->id, 'net_cost' => 12.50]);
        $unpriced = Product::create(['sku' => 'COST-B', 'description' => 'Unpriced', 'supplier_id' => $sup->id]);
        foreach ([[$priced, 3], [$unpriced, 2]] as [$p, $qty]) {
            DoorFrameHardwarePart::create([
                'configuration_id' => $config->id, 'part_label' => 'x', 'product_id' => $p->id,
                'quantity' => $qty, 'source_type' => 'manual',
            ]);
        }
        $pack = Product::create(['sku' => 'COST-P', 'description' => 'Pack', 'supplier_id' => $sup->id, 'net_cost' => 196, 'pack_size' => 50]);
        DoorFrameHardwarePart::create(['configuration_id' => $config->id, 'part_label' => 'pk', 'product_id' => $pack->id, 'quantity' => 2, 'source_type' => 'manual']);
        DoorFrameHardwarePart::create(['configuration_id' => $config->id, 'part_label' => 'special', 'quantity' => 1, 'source_type' => 'manual']);

        $this->getJson("/api/v1/door-frame-configurations/{$config->id}/cost")
            ->assertOk()
            ->assertJsonPath('total', 45.34)  // 37.50 + 2 x (196 / 50) for the pack item
            ->assertJsonPath('missing_price', 1)
            ->assertJsonPath('unpriced_special_order', 1)
            ->assertJsonCount(3, 'rows');
    }

    public function test_cost_pdf_export_renders_and_is_permission_gated(): void
    {
        $job = BusinessJob::create(['job_number' => 'C-2', 'job_name' => 'Pdf job', 'status' => 'active']);
        $config = DoorFrameConfiguration::create(['business_job_id' => $job->id, 'job_scope' => 'door_and_frame', 'status' => 'draft', 'quantity' => 1]);
        $sup = \App\Models\Supplier::create(['name' => 'S']);
        $p = Product::create(['sku' => 'PDF-A', 'description' => 'Pdf part', 'supplier_id' => $sup->id, 'net_cost' => 5]);
        DoorFrameHardwarePart::create(['configuration_id' => $config->id, 'part_label' => 'x', 'product_id' => $p->id, 'quantity' => 2, 'source_type' => 'manual']);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->get("/api/v1/door-frame-configurations/{$config->id}/cost/export")
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        Sanctum::actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]), ['*']);
        $this->getJson("/api/v1/door-frame-configurations/{$config->id}/cost/export")->assertForbidden();
    }
}
