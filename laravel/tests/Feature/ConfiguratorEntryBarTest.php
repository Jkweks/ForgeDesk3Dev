<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\DoorFrameConfiguration;
use App\Models\DoorFrameConfigurationDoor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfiguratorEntryBarTest extends TestCase
{
    use RefreshDatabase;

    public function test_entry_builder_selects_entries_from_a_wide_top_bar(): void
    {
        $html = $this->get('/config')->assertOk()->getContent();

        // Job -> entry selection lives in one full-width bar...
        foreach (['id="fb-entry-bar"', 'id="fb-list-job-filter"', 'id="fb-entry-select"', 'id="fb-entry-prev"', 'id="fb-entry-next"', 'id="fb-empty"'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        // ...so the old side list is gone and the working area is full width.
        $this->assertStringNotContainsString('fb-list-tbody', $html);
        $this->assertStringNotContainsString('col-lg-4', $html);
        $this->assertStringContainsString('<div class="col-12" id="fb-detail-col"', $html);
        $this->assertStringContainsString('onchange="fbOnJobChange()"', $html);
    }

    /** The bar's labels are built in JS from these list fields, so the API contract is pinned here. */
    public function test_configuration_list_exposes_the_fields_the_entry_bar_labels_use(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $job = BusinessJob::create(['job_number' => 'EB-1', 'job_name' => 'Bar Job', 'status' => 'active']);
        $config = DoorFrameConfiguration::create(['business_job_id' => $job->id, 'job_scope' => 'door_and_frame', 'status' => 'draft']);
        DoorFrameConfigurationDoor::create(['configuration_id' => $config->id, 'door_tag' => 'D-101']);

        $row = $this->getJson("/api/v1/door-frame-configurations?business_job_id={$job->id}&archived=0")
            ->assertOk()->json('configurations.0');

        $this->assertSame($config->id, $row['id']);
        $this->assertSame($job->id, $row['business_job_id']);
        foreach (['job_number', 'door_tags', 'scope_label', 'status', 'status_label'] as $field) {
            $this->assertArrayHasKey($field, $row);
        }
        $this->assertArrayHasKey('work_order_release_token', $row);
        $this->assertSame('EB-1', $row['job_number']);
        $this->assertStringContainsString('D-101', (string) $row['door_tags']);

        // Opening an entry uses business_job.id to keep the bar on the right job.
        $this->getJson("/api/v1/door-frame-configurations/{$config->id}")->assertOk()->assertJsonPath('configuration.business_job.id', $job->id);
    }
}
