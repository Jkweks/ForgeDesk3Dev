<?php

namespace Tests\Feature;

use App\Models\JobReservation;
use App\Models\JobReservationItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationCostSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function reservation(): JobReservation
    {
        $sup = Supplier::create(['name' => 'S']);
        $mk = fn ($sku, $extra) => Product::create(['sku' => $sku, 'description' => $sku, 'supplier_id' => $sup->id] + $extra);
        $job = \App\Models\BusinessJob::create(['job_number' => 'R-1', 'job_name' => 'Res job', 'status' => 'active']);
        $res = JobReservation::create(['business_job_id' => $job->id, 'job_number' => 'R-1', 'release_number' => '1', 'job_name' => 'Res job', 'requested_by' => 'T', 'status' => 'active']);
        foreach ([[$mk('RC-EACH', ['net_cost' => 10]), 3], [$mk('RC-PACK', ['net_cost' => 200, 'pack_size' => 50]), 5], [$mk('RC-NONE', []), 2]] as [$p, $qty]) {
            JobReservationItem::create(['reservation_id' => $res->id, 'product_id' => $p->id, 'requested_qty' => $qty, 'committed_qty' => $qty, 'consumed_qty' => 0]);
        }

        return $res;
    }

    public function test_summary_prices_pack_items_per_piece_and_flags_missing(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $res = $this->reservation();

        $this->getJson("/api/v1/job-reservations/{$res->id}/cost")
            ->assertOk()
            ->assertJsonPath('total', 50)   // 3 x 10 + 5 x (200 / 50)
            ->assertJsonPath('missing_price', 1)
            ->assertJsonCount(3, 'rows');
    }

    public function test_cost_and_export_are_limited_to_office_staff_managers_and_admins(): void
    {
        $res = $this->reservation();

        foreach (['admin', 'manager', 'office_staff'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'is_active' => true]), ['*']);
            $this->getJson("/api/v1/job-reservations/{$res->id}/cost")->assertOk();
            $this->get("/api/v1/job-reservations/{$res->id}/cost/export")->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }

        foreach (['fabricator', 'retail', 'viewer'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'is_active' => true]), ['*']);
            $this->getJson("/api/v1/job-reservations/{$res->id}/cost")->assertForbidden();
            $this->getJson("/api/v1/job-reservations/{$res->id}/cost/export")->assertForbidden();
        }
    }
}
