<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\FdWorkOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A work order's release label uses a custom `release_code` when set, otherwise
 * the auto "R{release_number}". The sequential release_number is unaffected.
 */
class WorkOrderReleaseCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin', 'is_active' => true, 'welcome_email_sent_at' => now(),
        ]), ['*']);
        $this->job = BusinessJob::create(['job_number' => 'J-77', 'job_name' => 'Test', 'status' => 'active']);
    }

    private BusinessJob $job;

    public function test_release_label_falls_back_to_r_number(): void
    {
        $res = $this->postJson('/api/v1/work-orders', ['business_job_id' => $this->job->id])->assertCreated();
        $res->assertJsonPath('release_number', 1)
            ->assertJsonPath('release_code', null)
            ->assertJsonPath('release_label', 'J-77-R1');
    }

    public function test_custom_release_code_replaces_the_r_token(): void
    {
        $res = $this->postJson('/api/v1/work-orders', [
            'business_job_id' => $this->job->id,
            'release_code' => 'Rev C',
        ])->assertCreated();

        $res->assertJsonPath('release_number', 1)
            ->assertJsonPath('release_code', 'Rev C')
            ->assertJsonPath('release_label', 'J-77-Rev C');

        $id = $res->json('id');
        $this->assertSame('J-77-Rev C', $this->getJson("/api/v1/work-orders/{$id}")->json('release_label'));
    }

    public function test_release_number_sequence_ignores_custom_codes(): void
    {
        $this->postJson('/api/v1/work-orders', ['business_job_id' => $this->job->id, 'release_code' => 'ALPHA'])->assertCreated();
        $second = $this->postJson('/api/v1/work-orders', ['business_job_id' => $this->job->id])->assertCreated();

        $second->assertJsonPath('release_number', 2)->assertJsonPath('release_label', 'J-77-R2');
    }

    public function test_patching_the_code_updates_the_label_and_blank_reverts(): void
    {
        $id = $this->postJson('/api/v1/work-orders', ['business_job_id' => $this->job->id])->json('id');

        $this->patchJson("/api/v1/work-orders/{$id}", ['release_code' => 'SPECIAL'])->assertOk();
        $this->assertSame('J-77-SPECIAL', $this->getJson("/api/v1/work-orders/{$id}")->json('release_label'));
        $this->assertSame('SPECIAL', FdWorkOrder::find($id)->release_code);

        $this->patchJson("/api/v1/work-orders/{$id}", ['release_code' => '  '])->assertOk();
        $wo = $this->getJson("/api/v1/work-orders/{$id}")->json();
        $this->assertNull($wo['release_code']);
        $this->assertSame('J-77-R1', $wo['release_label']);
    }
}
