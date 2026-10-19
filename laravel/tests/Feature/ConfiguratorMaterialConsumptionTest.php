<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\DoorFrameConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfiguratorMaterialConsumptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_prompts_for_scope_when_job_has_multiple_drafts_and_rejects_non_drafts(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($user, ['*']);
        $job = BusinessJob::create(['job_number' => 'MC-1', 'job_name' => 'Material job', 'status' => 'active']);
        $mk = fn ($status) => DoorFrameConfiguration::create(['business_job_id' => $job->id, 'job_scope' => 'door_and_frame', 'status' => $status, 'quantity' => 1]);
        $a = $mk('draft');
        $b = $mk('draft');
        $reserved = $mk('reserved');

        $this->getJson("/api/v1/door-frame-configurations/{$a->id}/material-consumption")
            ->assertOk()->assertJsonPath('needs_scope', true)->assertJsonCount(2, 'drafts');

        $this->getJson("/api/v1/door-frame-configurations/{$a->id}/material-consumption?scope=drafts")
            ->assertOk()->assertJsonCount(2, 'openings');

        $this->getJson("/api/v1/door-frame-configurations/{$a->id}/material-consumption?scope=this")
            ->assertOk()->assertJsonCount(1, 'openings');

        $this->getJson("/api/v1/door-frame-configurations/{$reserved->id}/material-consumption")
            ->assertStatus(422);
    }
}
