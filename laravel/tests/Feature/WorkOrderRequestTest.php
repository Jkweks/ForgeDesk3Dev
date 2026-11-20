<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\DrawingElevation;
use App\Models\DrawingSet;
use App\Models\PmFile;
use App\Models\FdElevationType;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\SystemNotification;
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

    /** A finished drawing set on the job with two approved elevations (W1 needs 4, W2 needs 2) and one not yet approved. */
    private function drawingSet(): array
    {
        $file = PmFile::create(['source' => 'upload', 'doc_type' => 'drawing_set', 'original_name' => 'shop.pdf', 'storage_path' => 'pm_files/x/shop.pdf',
            'sha256' => str_repeat('a', 64), 'size' => 10, 'business_job_id' => $this->job->id]);
        $set = DrawingSet::create(['business_job_id' => $this->job->id, 'pm_file_id' => $file->id, 'status' => 'done']);
        $mk = fn ($label, $total, $status) => DrawingElevation::create(['drawing_set_id' => $set->id, 'page' => 1, 'label' => $label, 'total_reqd' => $total, 'system' => 'TUBELITE TU14000 SERIES SF (2" x 4 1/2")',
            'finish' => 'CLEAR', 'arch_ref' => 'A-1', 'ro_w' => 60, 'ro_h' => 84.5, 'review_status' => $status]);

        return [$set, $mk('W1', 4, 'approved'), $mk('W2', 2, 'approved'), $mk('W3', 3, 'pending')];
    }

    public function test_a_request_is_tied_to_a_drawing_with_elevations_and_quantities(): void
    {
        [$set, $w1, $w2] = $this->drawingSet();
        $r = $this->postJson($this->url(), ['release_code' => 'B', 'drawing_set_id' => $set->id,
            'elevations' => [['drawing_elevation_id' => $w1->id, 'quantity' => 3], ['drawing_elevation_id' => $w2->id, 'quantity' => 2]]])
            ->assertCreated()->json();
        $this->assertSame('shop.pdf', $r['drawing_set']['file']['original_name']);
        $this->assertSame([3, 2], collect($r['elevations'])->pluck('quantity')->all());
        $this->assertSame(4, $r['elevations'][0]['total_reqd']);                          // a snapshot of what the drawing calls for
        $this->assertTrue(collect($r['checks'])->firstWhere('key', 'has_drawings')['ok']);   // the drawing itself is attached
        $this->assertTrue(collect($r['checks'])->firstWhere('key', 'has_elevations')['ok']);
        $this->assertSame('Shop drawings: shop.pdf', $r['files'][0]['label']);

        // the drawing's elevations say how many are already covered
        $this->assertSame(3, $w1->fresh()->load('workOrderLines')->requestedQuantity());

        // Fabrication approves: the lines go into the work order's notes
        $res = $this->postJson("/api/v1/work-order-requests/{$r['id']}/approve")->assertOk();
        $notes = (string) FdWorkOrder::findOrFail($res->json('work_order_id'))->notes;
        $this->assertStringContainsString('Elevations from shop.pdf', $notes);
        $this->assertStringContainsString('W1 x 3 of 4', $notes);
        $this->assertStringContainsString('W2 x 2 of 2', $notes);
        $this->assertStringContainsString('R.O. 60" x 84.5"', $notes);
    }

    public function test_only_approved_elevations_and_only_what_is_still_unrequested_can_be_released(): void
    {
        [$set, $w1, $w2, $w3] = $this->drawingSet();
        $post = fn (array $body) => $this->postJson($this->url(), ['drawing_set_id' => $set->id] + $body);

        $post(['elevations' => [['drawing_elevation_id' => $w3->id, 'quantity' => 1]]])->assertStatus(422);          // not approved
        $post(['elevations' => [['drawing_elevation_id' => $w1->id, 'quantity' => 5]]])->assertStatus(422);          // the drawing calls for 4
        $post(['elevations' => [['drawing_elevation_id' => $w1->id, 'quantity' => 0]]])->assertStatus(422);
        $post(['elevations' => [['drawing_elevation_id' => $w1->id, 'quantity' => 1], ['drawing_elevation_id' => $w1->id, 'quantity' => 1]]])->assertStatus(422);   // twice
        $this->postJson($this->url(), ['elevations' => [['drawing_elevation_id' => $w1->id, 'quantity' => 1]]])->assertStatus(422);     // no drawing named

        $first = $post(['elevations' => [['drawing_elevation_id' => $w1->id, 'quantity' => 3]]])->assertCreated()->json();
        $post(['elevations' => [['drawing_elevation_id' => $w1->id, 'quantity' => 2]]])->assertStatus(422);          // only 1 of 4 is left
        $post(['elevations' => [['drawing_elevation_id' => $w1->id, 'quantity' => 1]]])->assertCreated();            // the last one

        // withdrawing frees the quantity again
        $this->postJson("/api/v1/pm/work-order-requests/{$first['id']}/withdraw")->assertOk();
        $post(['elevations' => [['drawing_elevation_id' => $w1->id, 'quantity' => 3]]])->assertCreated();

        // another job's drawing cannot be used
        $other = BusinessJob::withoutGlobalScopes()->create(['job_number' => '2502', 'job_name' => 'Other', 'status' => 'active']);
        $this->postJson("/api/v1/pm/jobs/{$other->id}/work-order-requests", ['drawing_set_id' => $set->id, 'elevations' => [['drawing_elevation_id' => $w1->id, 'quantity' => 1]]])->assertStatus(422);
    }

    public function test_the_lines_survive_a_re_read_of_the_drawing_set(): void
    {
        [$set, $w1] = $this->drawingSet();
        $r = $this->postJson($this->url(), ['drawing_set_id' => $set->id, 'elevations' => [['drawing_elevation_id' => $w1->id, 'quantity' => 2]]])->assertCreated()->json();

        $set->elevations()->delete();                                   // a re-read replaces the elevation rows
        $line = \App\Models\WorkOrderRequestElevation::where('work_order_request_id', $r['id'])->firstOrFail();
        $this->assertNull($line->drawing_elevation_id);                 // the link is gone ...
        $this->assertSame('W1', $line->label);                          // ... the request is not
        $this->assertSame(2, $line->quantity);

        $new = DrawingElevation::create(['drawing_set_id' => $set->id, 'page' => 1, 'label' => 'W1', 'total_reqd' => 4, 'review_status' => 'approved']);
        \App\Models\WorkOrderRequestElevation::whereNull('drawing_elevation_id')->whereHas('request', fn ($q) => $q->where('drawing_set_id', $set->id))->get()
            ->each(fn ($l) => $l->update(['drawing_elevation_id' => $set->elevations()->where('label', $l->label)->value('id')]));   // what ImportDrawingSet does after a read
        $this->assertSame($new->id, $line->fresh()->drawing_elevation_id);
    }

    public function test_approving_seeds_the_fabrication_work_order_with_the_released_elevations(): void
    {
        $sf = FdElevationType::firstOrCreate(['name' => 'SF'], ['active' => true, 'sort_order' => 1]);      // the migrations seed it already
        [$set, $w1, $w2] = $this->drawingSet();
        $r = $this->postJson($this->url(), ['release_code' => 'A', 'requested_due_date' => now()->addWeek()->toDateString(), 'drawing_set_id' => $set->id,
            'elevations' => [['drawing_elevation_id' => $w1->id, 'quantity' => 3], ['drawing_elevation_id' => $w2->id, 'quantity' => 2]]])->assertCreated()->json();

        $wo = FdWorkOrder::findOrFail($this->postJson("/api/v1/work-order-requests/{$r['id']}/approve")->assertOk()->json('work_order_id'));

        $elevs = FdWoElevation::where('work_order_id', $wo->id)->orderBy('id')->get();
        $this->assertSame(['W1', 'W2'], $elevs->pluck('elevation_tag')->all());
        $this->assertSame([3, 2], $elevs->pluck('quantity')->all());
        $this->assertSame([$sf->id, $sf->id], $elevs->pluck('elevation_type_id')->all());       // "... SERIES SF (..." is the SF type
        $this->assertStringContainsString('Released from shop.pdf: W1 x 3 of 4', (string) $elevs[0]->notes);
        $this->assertSame($elevs->pluck('id')->all(), \App\Models\WorkOrderRequestElevation::where('work_order_request_id', $r['id'])->orderBy('id')->pluck('fd_wo_elevation_id')->all());
        $this->assertSame(now()->addWeek()->toDateString(), $wo->fresh()->due_date->toDateString());   // elevation dates drive the due date

        // the back-fill command does the same job and never makes a second copy
        $this->artisan('pm:seed-request-elevations', ['request' => $r['id']])->expectsOutputToContain('0 of 2 elevations still to seed')->assertSuccessful();
        $this->assertSame(2, FdWoElevation::where('work_order_id', $wo->id)->count());
        FdWoElevation::where('work_order_id', $wo->id)->first()->delete();                          // one is gone: only that one comes back
        $this->artisan('pm:seed-request-elevations', ['request' => $r['id']])->expectsOutputToContain('1 of 2 elevations still to seed')->assertSuccessful();
        $this->assertSame(2, FdWoElevation::where('work_order_id', $wo->id)->count());
    }

    public function test_seeded_elevations_get_the_stage_tier_named_in_the_drawings_system(): void
    {
        $sf = FdElevationType::firstOrCreate(['name' => 'SF'], ['active' => true, 'sort_order' => 1]);
        $default = \App\Models\FdStageTemplateSet::firstOrCreate(['elevation_type_id' => $sf->id, 'name' => 'Standard'], ['sort_order' => 1, 'is_default' => true]);
        $decoy = \App\Models\FdStageTemplateSet::create(['elevation_type_id' => $sf->id, 'name' => 'T14000 I/O', 'sort_order' => 2]);     // not TU14000
        $tier = \App\Models\FdStageTemplateSet::create(['elevation_type_id' => $sf->id, 'name' => 'TU14000', 'sort_order' => 3]);
        foreach (['Cut', 'Assemble', 'Glaze'] as $i => $step) {
            \App\Models\FdStageTemplate::create(['elevation_type_id' => $sf->id, 'template_set_id' => $tier->id, 'name' => $step, 'sort_order' => $i + 1, 'phase' => 'fabrication']);
        }
        [$set, $w1] = $this->drawingSet();                                   // system: "TUBELITE TU14000 SERIES SF (...)"
        $r = $this->postJson($this->url(), ['drawing_set_id' => $set->id, 'elevations' => [['drawing_elevation_id' => $w1->id, 'quantity' => 1]]])->assertCreated()->json();
        $wo = FdWorkOrder::findOrFail($this->postJson("/api/v1/work-order-requests/{$r['id']}/approve")->assertOk()->json('work_order_id'));

        $e = FdWoElevation::where('work_order_id', $wo->id)->firstOrFail();
        $this->assertSame($tier->id, $e->template_set_id);                    // the TU14000 tier, not the default and not "T14000 I/O"
        $this->assertSame(['Cut', 'Assemble', 'Glaze'], $e->stages()->pluck('name')->all());   // so it arrives with the stages Fabrication runs

        $seeder = app(\App\Services\Pm\WorkOrderElevationSeeder::class);
        $this->assertNull($seeder->tierFor($sf->id, 'KAWNEER 451T SF'));      // no tier named in the text: the type's default decides
        $this->assertNull($seeder->tierFor(null, 'TU14000'));
    }

    public function test_fabrication_edits_a_pending_request_and_the_pm_is_told(): void
    {
        $pm = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $fab = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        [$set, $w1, $w2, $w3] = $this->drawingSet();
        Sanctum::actingAs($pm, ['*']);
        $r = $this->postJson($this->url(), ['release_code' => 'B', 'drawing_set_id' => $set->id, 'scope' => 'Storefront', 'elevations' => [['drawing_elevation_id' => $w1->id, 'quantity' => 3]]])->assertCreated()->json();
        $edit = fn (array $body) => $this->patchJson("/api/v1/work-order-requests/{$r['id']}", $body);

        Sanctum::actingAs($fab, ['*']);
        $edit([])->assertStatus(422);                                                                                                  // nothing changed
        $edit(['elevations' => [['drawing_elevation_id' => $w3->id, 'quantity' => 1]]])->assertStatus(422);                           // not approved
        $edit(['elevations' => [['drawing_elevation_id' => $w1->id, 'quantity' => 9]]])->assertStatus(422);                           // more than the drawing calls for
        FdWorkOrder::create(['business_job_id' => $this->job->id, 'release_number' => 1, 'release_code' => 'TAKEN', 'date_issued' => today(), 'priority' => 1]);
        $edit(['release_code' => 'TAKEN'])->assertStatus(422);                                                                         // already a work order's code

        $res = $edit(['release_code' => 'C', 'requested_due_date' => '2026-12-24', 'scope' => 'Storefront, east wall only', 'change_note' => 'Glass for W2 is not in yet',
            'elevations' => [['drawing_elevation_id' => $w1->id, 'quantity' => 2], ['drawing_elevation_id' => $w2->id, 'quantity' => 1]]])->assertOk()->json();
        $this->assertSame('C', $res['release_code']);
        $this->assertSame([2, 1], collect($res['elevations'])->pluck('quantity')->all());
        $summary = $res['changes'][0]['summary'];
        foreach (['Release code: B → C', 'Needed by: none → 2026-12-24', 'Scope changed', 'W1: quantity 3 → 2', 'Added W2 × 1'] as $line) {
            $this->assertStringContainsString($line, $summary);
        }
        $this->assertSame('Glass for W2 is not in yet', $res['changes'][0]['note']);

        // the PM who asked gets it in their own bell, nobody else does
        $n = \App\Models\SystemNotification::where('type', 'work_order_request_changed')->firstOrFail();
        $this->assertSame($pm->id, $n->user_id);
        $this->assertStringContainsString('Glass for W2 is not in yet', $n->message);
        $this->assertSame($pm->id, $n->user_id);
        Sanctum::actingAs($pm, ['*']);
        $this->assertSame([$n->id], collect($this->getJson('/api/v1/notifications')->json())->where('type', 'work_order_request_changed')->pluck('id')->all());
        Sanctum::actingAs($fab, ['*']);
        $this->assertCount(0, collect($this->getJson('/api/v1/notifications')->json())->where('type', 'work_order_request_changed'));
        $this->postJson("/api/v1/notifications/{$n->id}/dismiss")->assertStatus(403);                                                   // not theirs to dismiss
        Sanctum::actingAs($pm, ['*']);
        $this->postJson("/api/v1/notifications/{$n->id}/dismiss")->assertOk();
        $this->assertSame('Glass for W2 is not in yet', $this->getJson($this->url())->json('0.changes.0.note'));                       // the PM sees what changed

        // editing your own request tells nobody; the quantity cap leaves the request's own lines out of the count
        Sanctum::actingAs($pm, ['*']);
        $this->patchJson("/api/v1/work-order-requests/{$r['id']}", ['elevations' => [['drawing_elevation_id' => $w1->id, 'quantity' => 4], ['drawing_elevation_id' => $w2->id, 'quantity' => 1]]])->assertOk();
        $this->assertSame(1, \App\Models\SystemNotification::where('type', 'work_order_request_changed')->count());

        // approving uses the edited lines; after that it can no longer be edited
        $wo = FdWorkOrder::findOrFail($this->postJson("/api/v1/work-order-requests/{$r['id']}/approve")->assertOk()->json('work_order_id'));
        $this->assertSame([4, 1], FdWoElevation::where('work_order_id', $wo->id)->orderBy('id')->pluck('quantity')->all());
        $this->patchJson("/api/v1/work-order-requests/{$r['id']}", ['scope' => 'too late'])->assertStatus(422);
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

    public function test_fabrication_approvers_get_a_bell_notification_until_it_is_decided(): void
    {
        $id = $this->postJson($this->url(), ['release_code' => 'N', 'scope' => 'x'])->assertCreated()->json('id');
        $this->assertSame(1, SystemNotification::active()->where('type', 'work_order_request')->count());

        // a fabrication approver (not an admin) sees it; someone without the permission does not
        \App\Models\Permission::firstOrCreate(['name' => 'fabrication.work-orders.create'], ['display_name' => 'x', 'description' => 'x', 'category' => 'fabrication']);
        $role = \App\Models\Role::firstOrCreate(['name' => 'wo_approver'], ['display_name' => 'WO approver']);
        $role->assignPermission('fabrication.work-orders.create');
        Sanctum::actingAs(User::factory()->create(['role' => 'wo_approver', 'is_active' => true]), ['*']);
        $this->assertCount(1, $this->getJson('/api/v1/notifications')->assertOk()->json());

        \App\Models\Role::firstOrCreate(['name' => 'nobody'], ['display_name' => 'Nobody']);
        Sanctum::actingAs(User::factory()->create(['role' => 'nobody', 'is_active' => true]), ['*']);
        $this->assertCount(0, $this->getJson('/api/v1/notifications')->json());

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->postJson("/api/v1/work-order-requests/{$id}/reject", ['decision_note' => 'no'])->assertOk();
        $this->assertSame(0, SystemNotification::active()->where('type', 'work_order_request')->count());
    }
}
