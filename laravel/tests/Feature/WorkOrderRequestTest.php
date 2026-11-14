<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\FdWorkOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** PM requests, Fabrication validates (docs/plans/pm-suite-integration.md, feature #15). */
class WorkOrderRequestTest extends TestCase
{
    use RefreshDatabase;

    private BusinessJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->job = BusinessJob::withoutGlobalScopes()->create(['job_number' => '2501', 'job_name' => 'W', 'status' => 'active']);
    }

    private function url(string $s = ''): string
    {
        return "/api/v1/pm/jobs/{$this->job->id}/work-order-requests{$s}";
    }

    public function test_request_approve_creates_a_work_order_and_keeps_the_history(): void
    {
        $r = $this->postJson($this->url(), ['release_code' => 'A', 'requested_due_date' => now()->addWeek()->toDateString(), 'scope' => 'Storefront elevations 1-3'])
            ->assertCreated()->assertJsonPath('status', 'pending')->json();
        $this->assertTrue(collect($r['checks'])->firstWhere('key', 'job_active')['ok']);
        $this->assertFalse(collect($r['checks'])->firstWhere('key', 'has_drawings')['ok']);

        $this->post("/api/v1/pm/work-order-requests/{$r['id']}/files", ['file' => UploadedFile::fake()->createWithContent('elev.pdf', '%PDF-1'), 'label' => 'Elevations'], ['Accept' => 'application/json'])->assertCreated();

        $pending = $this->getJson('/api/v1/work-order-requests')->assertOk()->json();
        $this->assertCount(1, $pending);
        $file = $pending[0]['files'][0];
        $this->get("/api/v1/work-order-requests/{$r['id']}/files/{$file['id']}")->assertOk()->assertDownload('elev.pdf');

        $res = $this->postJson("/api/v1/work-order-requests/{$r['id']}/approve", ['decision_note' => 'OK'])->assertOk();
        $wo = FdWorkOrder::findOrFail($res->json('work_order_id'));
        $this->assertSame($this->job->id, $wo->business_job_id);
        $this->assertSame('A', $wo->release_code);
        $this->assertStringContainsString('Storefront elevations', (string) $wo->notes);

        $this->assertSame('approved', $this->getJson($this->url())->json('0.status'));
        $this->postJson("/api/v1/work-order-requests/{$r['id']}/approve")->assertStatus(422);   // already decided
        $this->assertCount(0, $this->getJson('/api/v1/work-order-requests')->json());
    }

    public function test_reject_needs_a_reason_and_withdraw_works(): void
    {
        $a = $this->postJson($this->url(), ['scope' => 'x'])->json('id');
        $b = $this->postJson($this->url(), ['scope' => 'y'])->json('id');

        $this->postJson("/api/v1/work-order-requests/{$a}/reject", [])->assertStatus(422);
        $this->postJson("/api/v1/work-order-requests/{$a}/reject", ['decision_note' => 'Missing drawings'])->assertOk()->assertJsonPath('status', 'rejected');
        $this->postJson("/api/v1/pm/work-order-requests/{$b}/withdraw")->assertOk()->assertJsonPath('status', 'withdrawn');
        $this->postJson("/api/v1/pm/work-order-requests/{$b}/withdraw")->assertStatus(422);
        $this->assertSame(0, FdWorkOrder::count());
    }

    public function test_only_activated_jobs_can_be_requested_and_duplicates_are_flagged(): void
    {
        $bid = BusinessJob::withoutGlobalScopes()->create(['job_number' => '2502', 'job_name' => 'B', 'status' => 'won']);
        $this->postJson("/api/v1/pm/jobs/{$bid->id}/work-order-requests", ['scope' => 'x'])->assertStatus(422);

        $this->postJson($this->url(), ['release_code' => 'B'])->assertCreated();
        $second = $this->postJson($this->url(), ['release_code' => 'B'])->assertCreated()->json();
        $this->assertFalse(collect($second['checks'])->firstWhere('key', 'release_code_free')['ok']);

        // a job that goes on hold before approval cannot be approved
        $this->job->update(['status' => 'on_hold']);
        $this->postJson("/api/v1/work-order-requests/{$second['id']}/approve")->assertStatus(422);
    }

    public function test_pm_scope_permissions_and_pages(): void
    {
        $other = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $id = $this->postJson($this->url(), ['scope' => 'x'])->json('id');

        Sanctum::actingAs($other, ['*']);
        $this->getJson($this->url())->assertNotFound();                                       // not their job
        $this->postJson("/api/v1/pm/work-order-requests/{$id}/withdraw")->assertNotFound();
        $this->getJson('/api/v1/work-order-requests')->assertForbidden();                    // fabrication side needs its own permission

        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->get('/fabrication/work-order-requests')->assertOk()->assertSee('Work Order Requests');
        $this->get("/pm/jobs/{$this->job->id}")->assertOk()->assertSee('Request work order');
    }
}
