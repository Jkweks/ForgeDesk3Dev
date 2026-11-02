<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\FieldSyncLog;
use App\Models\Shipment;
use App\Models\ShipmentCheckItem;
use App\Models\ShipmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShipmentInspectionTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['fleet.sync.enabled' => true]);
        cache()->flush();

        $s = Schema::connection('fieldsync');
        $s->create('fa_assets', function ($t) {
            $t->string('asset_number')->primary();
            $t->string('name');
            $t->string('type_name');
            $t->string('meter_type')->nullable();
            $t->string('status');
            $t->decimal('current_meter', 12, 1)->nullable();
            $t->string('current_job_label')->nullable();
            $t->boolean('is_active')->default(true);
            $t->string('synced_at')->nullable();
        });
        $s->create('fa_checklists', function ($t) {
            $t->string('type_name')->primary();
            $t->string('template_name');
            $t->text('items');
            $t->string('updated_at')->nullable();
        });
        $s->create('fa_submissions', function ($t) {
            $t->id();
            $t->string('submission_uuid')->unique();
            $t->string('kind');
            $t->string('asset_number');
            $t->string('submitted_by')->nullable();
            $t->string('submitted_at')->nullable();
            $t->text('payload');
            $t->string('ingested_at')->nullable();
        });
        $s->create('fa_users', function ($t) {
            $t->unsignedBigInteger('forgedesk_user_id')->primary();
            $t->string('login');
            $t->string('display_name');
            $t->string('password_hash');
            $t->boolean('is_active')->default(true);
            $t->string('synced_at')->nullable();
        });
        $s->create('fa_shipments', function ($t) {
            $t->string('shipment_number')->primary();
            $t->string('job_label')->nullable();
            $t->string('destination')->nullable();
            $t->string('ship_date')->nullable();
            $t->text('checklist');
            $t->boolean('is_active')->default(true);
            $t->string('synced_at')->nullable();
        });
        $s->create('fa_photos', function ($t) {
            $t->id();
            $t->string('submission_uuid');
            $t->string('filename');
            $t->string('mime')->nullable();
            $t->text('data_b64');
        });
    }

    private function as(string $role): User
    {
        $u = User::factory()->create(['role' => $role, 'is_active' => true]);
        Sanctum::actingAs($u, ['*']);

        return $u;
    }

    private function shipment(string $status = 'draft', array $types = ['Metal', 'Glass']): Shipment
    {
        $job = BusinessJob::create(['job_number' => 'A-'.random_int(1000, 99999), 'job_name' => 'Tower', 'status' => 'active']);
        $s = Shipment::create(['shipment_number' => Shipment::nextNumber(), 'business_job_id' => $job->id, 'status' => $status, 'destination' => 'Site 1']);
        foreach ($types as $name) {
            $s->loads()->create(['shipment_type_id' => ShipmentType::where('name', $name)->value('id')]);
        }

        return $s;
    }

    /** Answers that pass every item of a phase, by item id. */
    private function passAll(Shipment $s, string $phase): array
    {
        $a = [];
        foreach (app(\App\Services\Shipping\ShipmentInspectionService::class)->items($s, $phase) as $i) {
            $a[$i->id] = ['value' => $i->input_type === 'number' ? '4' : 'pass'];
        }

        return $a;
    }

    public function test_starter_types_are_seeded_with_both_phases(): void
    {
        foreach (['Metal', 'Glass', 'Doors'] as $n) {
            $t = ShipmentType::where('name', $n)->firstOrFail();
            $this->assertTrue($t->itemsFor('pre')->exists());
            $this->assertTrue($t->itemsFor('post')->exists());
        }
    }

    public function test_admin_can_add_a_type_with_items_and_it_drives_the_checklist(): void
    {
        $this->as('admin');
        $type = $this->postJson('/api/v1/shipment-types', ['name' => 'Louvers'])->assertCreated()->json();
        $this->postJson("/api/v1/shipment-types/{$type['id']}/items", ['phase' => 'pre', 'label' => 'Blade count', 'input_type' => 'number', 'unit' => 'ea'])->assertCreated();
        $this->postJson("/api/v1/shipment-types/{$type['id']}/items", ['phase' => 'post', 'label' => 'Finish', 'input_type' => 'choice'])->assertStatus(422); // choice needs options
        $this->postJson("/api/v1/shipment-types/{$type['id']}/items", ['phase' => 'post', 'label' => 'Finish', 'input_type' => 'choice', 'options' => ['Good', 'Scuffed']])->assertCreated();

        $id = $this->postJson('/api/v1/shipments', ['loads' => [['shipment_type_id' => $type['id']]]])->assertCreated()
            ->assertJsonPath('pre_checklist.0.type_name', 'Louvers')->assertJsonPath('pre_checklist.0.items.0.label', 'Blade count')
            ->assertJsonPath('post_checklist.0.items.0.options.1', 'Scuffed')->json('id');
        $this->assertNotNull($id);
    }

    public function test_type_management_requires_permission(): void
    {
        $this->as('office_staff');
        $this->postJson('/api/v1/shipment-types', ['name' => 'Nope'])->assertForbidden();
        $this->getJson('/api/v1/shipment-types')->assertOk();
    }

    public function test_a_used_type_cannot_be_deleted(): void
    {
        $this->as('admin');
        $this->shipment();
        $this->deleteJson('/api/v1/shipment-types/'.ShipmentType::where('name', 'Metal')->value('id'))->assertStatus(422);
    }

    public function test_pre_inspection_saves_with_photos_and_moves_shipment_forward(): void
    {
        $this->as('admin');
        $s = $this->shipment();
        $answers = $this->passAll($s, 'pre');
        $firstId = array_key_first($answers);
        $answers[$firstId]['photos'] = [0];

        $this->post("/api/v1/shipments/{$s->id}/pre-inspection", [
            'answers' => json_encode($answers), 'notes' => 'ok',
            'photos' => [UploadedFile::fake()->createWithContent('a.png', base64_decode(self::PNG)), UploadedFile::fake()->createWithContent('b.png', base64_decode(self::PNG))],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('status', 'pre_check_done');

        $insp = $s->inspections()->first();
        $this->assertSame('pass', $insp->result);
        $this->assertSame(2, $insp->photos()->count());
        $this->assertSame(1, $insp->photos()->whereNotNull('item_id')->count());
        Storage::disk('local')->assertExists($insp->photos()->first()->file_path);
    }

    public function test_required_items_and_failure_photos_are_enforced(): void
    {
        $this->as('admin');
        $s = $this->shipment('draft', ['Glass']);
        $answers = $this->passAll($s, 'pre');
        $damage = ShipmentCheckItem::where('label', 'No chips, cracks or edge damage')->where('phase', 'pre')->first();
        $answers[$damage->id] = ['value' => 'fail'];   // photo_required, no photo
        $this->post("/api/v1/shipments/{$s->id}/pre-inspection", ['answers' => json_encode($answers)], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(["items.{$damage->id}"]);

        unset($answers[$damage->id]);
        $this->post("/api/v1/shipments/{$s->id}/pre-inspection", ['answers' => json_encode($answers)], ['Accept' => 'application/json'])
            ->assertStatus(422);  // required item missing
    }

    public function test_critical_failure_fails_inspection_and_blocks_shipping(): void
    {
        $this->as('admin');
        $s = $this->shipment('draft', ['Glass']);
        $answers = $this->passAll($s, 'pre');
        $damage = ShipmentCheckItem::where('label', 'No chips, cracks or edge damage')->where('phase', 'pre')->first();
        $answers[$damage->id] = ['value' => 'fail', 'photos' => [0]];
        $this->post("/api/v1/shipments/{$s->id}/pre-inspection", [
            'answers' => json_encode($answers), 'photos' => [UploadedFile::fake()->createWithContent('a.png', base64_decode(self::PNG))],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('status', 'draft');

        $this->assertSame('fail', $s->inspections()->first()->result);
        $this->postJson("/api/v1/shipments/{$s->id}/ship")->assertStatus(422);
        $this->assertDatabaseHas('system_notifications', ['type' => 'shipment_pre_fail']);
    }

    public function test_non_image_upload_is_never_stored(): void
    {
        $this->as('admin');
        $s = $this->shipment('draft', ['Metal']);
        $this->post("/api/v1/shipments/{$s->id}/pre-inspection", [
            'answers' => json_encode($this->passAll($s, 'pre')),
            'photos' => [UploadedFile::fake()->createWithContent('x.png', '<?php echo 1;')],
        ], ['Accept' => 'application/json']);

        // Whether the validator or the service catches it, a disguised file must not end up on disk.
        $this->assertSame(0, \App\Models\ShipmentInspectionPhoto::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_ship_requires_a_passed_pre_check_and_then_publishes_to_field(): void
    {
        $this->as('admin');
        $s = $this->shipment();
        $this->postJson("/api/v1/shipments/{$s->id}/ship")->assertStatus(422);

        $this->post("/api/v1/shipments/{$s->id}/pre-inspection", ['answers' => json_encode($this->passAll($s, 'pre'))], ['Accept' => 'application/json'])->assertCreated();
        $this->postJson("/api/v1/shipments/{$s->id}/ship")->assertOk()->assertJsonPath('status', 'shipped');

        $this->artisan('fleet:sync-out')->assertSuccessful();
        $row = DB::connection('fieldsync')->table('fa_shipments')->where('shipment_number', $s->shipment_number)->first();
        $this->assertEquals(1, $row->is_active);
        $this->assertStringContainsString('Tower', $row->job_label);
        $list = json_decode($row->checklist, true);
        $this->assertSame(['Metal', 'Glass'], array_column($list, 'type_name'));
        $this->assertArrayNotHasKey('critical', $list[0]['items'][0]);   // criticality never leaves ForgeDesk
    }

    public function test_unshipped_shipments_are_not_published_and_delivered_ones_are_hidden(): void
    {
        $draft = $this->shipment('draft');
        $shipped = $this->shipment('shipped');
        $this->artisan('fleet:sync-out')->assertSuccessful();
        $remote = DB::connection('fieldsync')->table('fa_shipments');
        $this->assertSame([$shipped->shipment_number], $remote->pluck('shipment_number')->all());

        $shipped->update(['status' => 'delivered']);
        $this->artisan('fleet:sync-out')->assertSuccessful();
        $this->assertEquals(0, DB::connection('fieldsync')->table('fa_shipments')->value('is_active'));
    }

    private function fieldSubmit(Shipment $s, array $items, array $extra = [], ?string $uuid = null): string
    {
        $uuid ??= (string) \Illuminate\Support\Str::uuid();
        DB::connection('fieldsync')->table('fa_submissions')->insert([
            'submission_uuid' => $uuid, 'kind' => 'shipment_inspection', 'asset_number' => $extra['number'] ?? $s->shipment_number,
            'submitted_by' => 'Crew Lead', 'submitted_at' => now()->toDateTimeString(),
            'payload' => json_encode(['notes' => 'on site', 'items' => $items]),
        ]);

        return $uuid;
    }

    private function postItems(Shipment $s, string $result = 'pass'): array
    {
        $items = [];
        foreach (app(\App\Services\Shipping\ShipmentInspectionService::class)->items($s, 'post') as $i) {
            $items[] = ['item_id' => $i->id, 'value' => $i->input_type === 'number' ? '4' : $result];
        }

        return $items;
    }

    public function test_field_post_inspection_is_ingested_with_photos_and_marks_delivered(): void
    {
        $s = $this->shipment('shipped');
        $items = $this->postItems($s);
        $items[0]['photos'] = [0];
        $uuid = $this->fieldSubmit($s, $items);
        DB::connection('fieldsync')->table('fa_photos')->insert(['submission_uuid' => $uuid, 'filename' => 'p.png', 'data_b64' => self::PNG]);

        $this->artisan('fleet:sync-in')->assertSuccessful();

        $this->assertSame('ingested', FieldSyncLog::where('submission_uuid', $uuid)->value('status'));
        $this->assertSame('delivered', $s->fresh()->status);
        $insp = $s->inspections()->where('phase', 'post')->first();
        $this->assertSame('field_app', $insp->source);
        $this->assertSame('Crew Lead', $insp->submitted_by_external);
        $this->assertSame(1, $insp->photos()->whereNotNull('item_id')->count());
        $this->assertSame(0, DB::connection('fieldsync')->table('fa_photos')->count());
    }

    public function test_field_failure_flags_delivery_issues_and_alerts(): void
    {
        $s = $this->shipment('shipped', ['Glass']);
        $items = $this->postItems($s);
        $damage = ShipmentCheckItem::where('label', 'No broken or chipped lites')->where('phase', 'post')->first();
        foreach ($items as &$i) {
            if ($i['item_id'] === $damage->id) {
                $i['value'] = 'fail';
            }
        }
        $this->fieldSubmit($s, $items);
        $this->artisan('fleet:sync-in')->assertSuccessful();

        $this->assertSame('delivered_issues', $s->fresh()->status);
        $this->assertSame('fail', $s->inspections()->first()->result);   // criticality came from ForgeDesk, not the client
        $this->assertDatabaseHas('system_notifications', ['type' => 'shipment_post_issues']);
    }

    public function test_field_submission_is_idempotent(): void
    {
        $s = $this->shipment('shipped');
        $uuid = $this->fieldSubmit($s, $this->postItems($s));
        $this->artisan('fleet:sync-in')->assertSuccessful();
        DB::connection('fieldsync')->table('fa_submissions')->update(['ingested_at' => null]);   // replay
        $this->artisan('fleet:sync-in')->assertSuccessful();

        $this->assertSame(1, $s->inspections()->where('phase', 'post')->count());
        $this->assertSame(1, FieldSyncLog::where('submission_uuid', $uuid)->count());
    }

    public function test_field_submission_for_unknown_or_unshipped_shipment_is_not_applied(): void
    {
        $draft = $this->shipment('draft');
        $u1 = $this->fieldSubmit($draft, $this->postItems($draft));
        $u2 = $this->fieldSubmit($draft, $this->postItems($draft), ['number' => 'SHP-99999']);
        $this->artisan('fleet:sync-in')->assertSuccessful();

        $this->assertSame('needs_review', FieldSyncLog::where('submission_uuid', $u1)->value('status'));
        $this->assertSame('rejected', FieldSyncLog::where('submission_uuid', $u2)->value('status'));
        $this->assertSame(0, $draft->inspections()->count());
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_field_submission_with_bad_answers_is_rejected(): void
    {
        $s = $this->shipment('shipped');
        $u = $this->fieldSubmit($s, [['item_id' => 999999, 'value' => 'pass']]);
        $this->artisan('fleet:sync-in')->assertSuccessful();
        $this->assertSame('rejected', FieldSyncLog::where('submission_uuid', $u)->value('status'));
        $this->assertSame('shipped', $s->fresh()->status);
    }

    public function test_queue_lists_window_past_due_and_unscheduled_but_not_cancelled(): void
    {
        $this->as('admin');
        $in = $this->shipment('draft');
        $in->update(['ship_date' => now()->addDays(2)->toDateString()]);
        $late = $this->shipment('pre_check_done');
        $late->update(['ship_date' => now()->subDays(20)->toDateString()]);
        $none = $this->shipment('draft');
        $gone = $this->shipment('cancelled');
        $old = $this->shipment('delivered');
        $old->update(['ship_date' => now()->subDays(20)->toDateString()]);

        $res = $this->getJson('/api/v1/shipments/queue?from='.now()->startOfWeek()->toDateString())->assertOk();
        $numbers = collect($res->json('shipments'))->pluck('shipment_number');
        $this->assertEqualsCanonicalizing([$in->shipment_number, $late->shipment_number, $none->shipment_number], $numbers->all());
        $this->assertFalse(collect($res->json('shipments'))->firstWhere('shipment_number', $in->shipment_number)['job'] === null);
    }

    public function test_dragging_reschedules_only_shipments_that_have_not_left(): void
    {
        $this->as('admin');
        $s = $this->shipment('pre_check_done');
        $date = now()->addDays(3)->toDateString();
        $this->patchJson("/api/v1/shipments/{$s->id}/schedule", ['ship_date' => $date])->assertOk();
        $this->assertSame($date, $s->fresh()->ship_date->toDateString());

        $this->patchJson("/api/v1/shipments/{$s->id}/schedule", ['ship_date' => null])->assertOk();
        $this->assertNull($s->fresh()->ship_date);

        $s->update(['status' => 'shipped']);
        $this->patchJson("/api/v1/shipments/{$s->id}/schedule", ['ship_date' => $date])->assertStatus(422);
        $this->patchJson("/api/v1/shipments/{$s->id}/schedule", [])->assertStatus(422);   // ship_date must be present (null clears)
    }

    public function test_scheduling_requires_manage_permission(): void
    {
        $s = $this->shipment('draft');
        $this->as('viewer');
        $this->patchJson("/api/v1/shipments/{$s->id}/schedule", ['ship_date' => now()->toDateString()])->assertForbidden();
    }

    public function test_jobs_list_carries_shipment_counts(): void
    {
        $this->as('admin');
        $a = $this->shipment('shipped');
        $b = $this->shipment('cancelled');
        $b->update(['business_job_id' => $a->business_job_id]);
        $a->job; 
        $row = collect($this->getJson('/api/v1/business-jobs')->assertOk()->json('jobs'))->firstWhere('id', $a->business_job_id);
        $this->assertSame(1, $row['shipments_count']);          // cancelled excluded
        $this->assertSame(1, $row['shipments_transit_count']);
        $this->assertSame(0, $row['shipments_issues_count']);
    }
}
