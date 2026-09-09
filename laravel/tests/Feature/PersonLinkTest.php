<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\JobReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Linking reservations / jobs to real user accounts via the "Requested by" and
 * "Project manager" pickers, while keeping the free-text label in sync.
 */
class PersonLinkTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin', 'is_active' => true,
            'first_name' => 'Ada', 'last_name' => 'Admin', 'name' => 'Ada Admin',
            'welcome_email_sent_at' => now(),
        ]);
    }

    public function test_people_endpoint_lists_active_users_last_first_and_needs_no_special_permission(): void
    {
        $pm = User::factory()->create(['role' => 'manager', 'is_active' => true, 'first_name' => 'John', 'last_name' => 'Smith', 'name' => 'John Smith']);
        User::factory()->create(['role' => 'viewer', 'is_active' => false, 'first_name' => 'In', 'last_name' => 'Active', 'name' => 'In Active']);
        $office = User::factory()->create(['role' => 'office_staff', 'is_active' => true, 'first_name' => 'Olive', 'last_name' => 'Office', 'name' => 'Olive Office']);

        Sanctum::actingAs($office, ['*']); // no users.view permission

        $rows = $this->getJson('/api/v1/people')->assertOk()->json();
        $labels = collect($rows)->pluck('label');

        $this->assertContains('Smith, John', $labels);
        $this->assertContains('Office, Olive', $labels);
        $this->assertNotContains('Active, In', $labels, 'inactive users are excluded');
        // Sorted by last name.
        $this->assertLessThan($labels->search('Smith, John'), $labels->search('Office, Olive'));
        $this->assertSame($pm->id, collect($rows)->firstWhere('label', 'Smith, John')['id']);
    }

    public function test_creating_a_job_with_a_pm_id_links_it_and_syncs_the_label(): void
    {
        Sanctum::actingAs($this->admin(), ['*']);
        $pm = User::factory()->create(['role' => 'manager', 'is_active' => true, 'first_name' => 'Jane', 'last_name' => 'Doe', 'name' => 'Jane Doe']);

        $res = $this->postJson('/api/v1/business-jobs', [
            'job_number' => 'J-100', 'job_name' => 'Test', 'project_manager_id' => $pm->id,
        ])->assertCreated();

        $job = BusinessJob::where('job_number', 'J-100')->first();
        $this->assertSame($pm->id, $job->project_manager_id);
        $this->assertSame('Doe, Jane', $job->project_manager);

        $row = collect($this->getJson('/api/v1/business-jobs')->json('jobs'))->firstWhere('job_number', 'J-100');
        $this->assertSame($pm->id, $row['project_manager_id']);
        $this->assertSame('Doe, Jane', $row['project_manager']);

        // Re-point to nobody clears the link but the update still succeeds.
        $this->putJson("/api/v1/business-jobs/{$job->id}", ['project_manager_id' => null])->assertOk();
        $this->assertNull($job->fresh()->project_manager_id);
        $this->assertNull($job->fresh()->project_manager);
    }

    public function test_a_bare_pm_string_still_works_unlinked(): void
    {
        Sanctum::actingAs($this->admin(), ['*']);

        $this->postJson('/api/v1/business-jobs', [
            'job_number' => 'J-200', 'job_name' => 'Legacy', 'project_manager' => ' External Consultant',
        ])->assertCreated();

        $job = BusinessJob::where('job_number', 'J-200')->first();
        $this->assertNull($job->project_manager_id);
        $this->assertSame('External Consultant', $job->project_manager);
    }

    public function test_reservation_links_and_syncs_requested_by(): void
    {
        Sanctum::actingAs($this->admin(), ['*']);
        $req = User::factory()->create(['role' => 'office_staff', 'is_active' => true, 'first_name' => 'Rob', 'last_name' => 'Requester', 'name' => 'Rob Requester']);
        $product = \App\Models\Product::create([
            'sku' => 'SKU-PL-1', 'description' => 'Test part', 'quantity_on_hand' => 50,
        ]);
        $job = BusinessJob::create(['job_number' => 'JOB-9', 'job_name' => 'Nine', 'status' => 'active']);

        $this->postJson("/api/v1/business-jobs/{$job->id}/reservations", [
            'requested_by_id' => $req->id,
            'items' => [['product_id' => $product->id, 'requested_qty' => 2, 'committed_qty' => 2]],
        ])->assertSuccessful();

        $reservation = JobReservation::where('business_job_id', $job->id)->latest('id')->first();
        $this->assertSame($req->id, $reservation->requested_by_id);
        $this->assertSame('Requester, Rob', $reservation->requested_by);

        // Surfaced in the job's reservation list.
        $row = collect($this->getJson("/api/v1/business-jobs/{$job->id}/reservations")->json('reservations') ?? [])
            ->firstWhere('id', $reservation->id);
        $this->assertSame($req->id, $row['requested_by_id'] ?? null);

        // Update to a different person re-syncs both; blank clears the link.
        $other = User::factory()->create(['role' => 'manager', 'is_active' => true, 'first_name' => 'Sue', 'last_name' => 'Swap', 'name' => 'Sue Swap']);
        $this->putJson("/api/v1/job-reservations/{$reservation->id}", ['requested_by_id' => $other->id])->assertOk();
        $this->assertSame($other->id, $reservation->fresh()->requested_by_id);
        $this->assertSame('Swap, Sue', $reservation->fresh()->requested_by);

        $this->putJson("/api/v1/job-reservations/{$reservation->id}", ['requested_by_id' => null])->assertOk();
        $this->assertNull($reservation->fresh()->requested_by_id);
    }

    public function test_sort_name_falls_back_when_no_first_last(): void
    {
        $u = User::factory()->make(['first_name' => null, 'last_name' => null, 'name' => 'Legacy Name']);
        $this->assertSame('Legacy Name', $u->sort_name);

        $u2 = User::factory()->make(['first_name' => 'Only', 'last_name' => '', 'name' => '']);
        $this->assertSame('Only', $u2->sort_name);
    }
}
