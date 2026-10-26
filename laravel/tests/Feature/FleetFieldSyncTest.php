<?php

namespace Tests\Feature;

use App\Models\FieldAsset;
use App\Models\FieldAssetDocument;
use App\Models\FieldAssetInspection;
use App\Models\FieldAssetType;
use App\Models\FieldAssetWorkOrder;
use App\Models\FieldSyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The field app's database is stood in for by the in-memory `fieldsync` sqlite connection. */
class FleetFieldSyncTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        config(['fleet.sync.enabled' => true]);
        Storage::fake('local');
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
        $s->create('fa_photos', function ($t) {
            $t->id();
            $t->string('submission_uuid');
            $t->string('filename');
            $t->string('mime')->nullable();
            $t->text('data_b64');
        });
    }

    private function asset(array $attrs = []): FieldAsset
    {
        $type = FieldAssetType::where('name', $attrs['type'] ?? 'Truck')->first();
        unset($attrs['type']);

        return FieldAsset::create($attrs + ['asset_number' => 'FA-0001', 'name' => 'F-250', 'field_asset_type_id' => $type->id]);
    }

    private function submit(string $kind, string $asset, array $payload, array $extra = []): string
    {
        $uuid = $extra['uuid'] ?? (string) \Illuminate\Support\Str::uuid();
        DB::connection('fieldsync')->table('fa_submissions')->insert([
            'submission_uuid' => $uuid, 'kind' => $kind, 'asset_number' => $asset,
            'submitted_by' => $extra['by'] ?? 'Crew Lead', 'submitted_at' => $extra['at'] ?? now()->toDateTimeString(),
            'payload' => is_string($payload) ? $payload : json_encode($payload),
        ]);

        return $uuid;
    }

    private function photo(string $uuid, string $b64, string $name = 'p.png'): void
    {
        DB::connection('fieldsync')->table('fa_photos')->insert(['submission_uuid' => $uuid, 'filename' => $name, 'data_b64' => $b64]);
    }

    public function test_commands_do_nothing_when_sync_is_disabled(): void
    {
        config(['fleet.sync.enabled' => false]);
        $this->asset();
        $this->artisan('fleet:sync-out')->assertSuccessful();
        $this->assertSame(0, DB::connection('fieldsync')->table('fa_assets')->count());
    }

    public function test_publish_sends_minimal_projection_and_hides_retired_assets(): void
    {
        $this->asset(['purchase_cost' => 50000, 'vin' => '1FT123', 'notes' => 'secret']);
        $this->asset(['asset_number' => 'FA-0002', 'name' => 'Old', 'status' => 'retired']);
        DB::connection('fieldsync')->table('fa_assets')->insert(['asset_number' => 'FA-0002', 'name' => 'Old', 'type_name' => 'Truck', 'status' => 'active', 'is_active' => true]);

        $this->artisan('fleet:sync-out')->assertSuccessful();

        $remote = DB::connection('fieldsync');
        $row = (array) $remote->table('fa_assets')->where('asset_number', 'FA-0001')->first();
        $this->assertSame('Truck', $row['type_name']);
        $this->assertEqualsCanonicalizing(
            ['asset_number', 'name', 'type_name', 'meter_type', 'status', 'current_meter', 'current_job_label', 'is_active', 'synced_at'],
            array_keys($row)
        );
        $this->assertStringNotContainsString('50000', json_encode($row));
        $this->assertStringNotContainsString('1FT123', json_encode($row));
        $this->assertEquals(0, $remote->table('fa_assets')->where('asset_number', 'FA-0002')->value('is_active'));
        $items = json_decode($remote->table('fa_checklists')->where('type_name', 'Truck')->value('items'), true);
        $this->assertSame(['label' => 'Brakes'], $items[0]); // no "critical" flag leaves ForgeDesk
    }

    public function test_inspection_is_ingested_and_criticality_comes_from_forgedesk_not_the_client(): void
    {
        $asset = $this->asset();
        $uuid = $this->submit('inspection', 'FA-0001', [
            'template_name' => 'Pre-trip', 'meter' => 1500,
            'items' => [
                ['label' => 'Brakes', 'result' => 'fail', 'comment' => 'spongy', 'critical' => false], // client lies
                ['label' => 'Horn / wipers', 'result' => 'pass'],
            ],
        ]);
        $this->photo($uuid, self::PNG);

        $this->artisan('fleet:sync-in')->assertSuccessful();

        $insp = FieldAssetInspection::first();
        $this->assertSame('fail', $insp->result);
        $this->assertSame('field_app', $insp->source);
        $this->assertSame('Crew Lead', $insp->submitted_by_external);
        $this->assertTrue($insp->items()->where('label', 'Brakes')->first()->critical);
        $this->assertEquals(1500, $asset->fresh()->current_meter);
        $wo = FieldAssetWorkOrder::first();
        $this->assertSame('inspection_defect', $wo->type);
        $this->assertSame('field_app', $wo->source);
        $this->assertSame(1, FieldAssetDocument::where('inspection_id', $insp->id)->where('doc_type', 'photo')->count());
        Storage::disk('local')->assertExists(FieldAssetDocument::first()->file_path);
        $this->assertSame('ingested', FieldSyncLog::first()->status);
        $this->assertNotNull(DB::connection('fieldsync')->table('fa_submissions')->value('ingested_at'));
        $this->assertSame(0, DB::connection('fieldsync')->table('fa_photos')->count()); // purged after ingest
        $this->assertSame('active', $asset->fresh()->status); // auto out-of-service is off by default
        $this->assertGreaterThan(0, \App\Models\SystemNotification::count());
    }

    public function test_critical_fail_takes_asset_out_of_service_only_when_type_flag_is_on(): void
    {
        $asset = $this->asset(['type' => 'Swing Stage']);
        FieldAssetType::where('name', 'Swing Stage')->update(['auto_out_of_service_on_fail' => true]);
        $this->submit('inspection', 'FA-0001', ['items' => [['label' => 'Suspension wire rope', 'result' => 'fail']]]);

        $this->artisan('fleet:sync-in')->assertSuccessful();

        $this->assertSame('out_of_service', $asset->fresh()->status);
    }

    public function test_resubmitting_the_same_uuid_is_idempotent(): void
    {
        $this->asset();
        $uuid = $this->submit('defect', 'FA-0001', ['title' => 'Cracked mirror']);
        $this->artisan('fleet:sync-in')->assertSuccessful();

        DB::connection('fieldsync')->table('fa_submissions')->update(['ingested_at' => null]); // replay
        $this->artisan('fleet:sync-in')->assertSuccessful();

        $this->assertSame(1, FieldAssetWorkOrder::count());
        $this->assertSame(1, FieldSyncLog::where('submission_uuid', $uuid)->count());
        $this->assertNotNull(DB::connection('fieldsync')->table('fa_submissions')->value('ingested_at'));
    }

    public function test_defect_report_opens_a_field_work_order(): void
    {
        $this->asset();
        $this->submit('defect', 'FA-0001', ['title' => 'Hydraulic leak', 'description' => 'Under the bed', 'severity' => 'high']);

        $this->artisan('fleet:sync-in')->assertSuccessful();

        $wo = FieldAssetWorkOrder::first();
        $this->assertSame('repair', $wo->type);
        $this->assertSame('high', $wo->priority);
        $this->assertSame('Crew Lead', $wo->reported_by_external);
    }

    public function test_lower_meter_reading_is_held_for_review_then_can_be_applied(): void
    {
        $asset = $this->asset();
        $asset->forceFill(['current_meter' => 20000])->save();
        $this->submit('meter', 'FA-0001', ['reading' => 2000]);

        $this->artisan('fleet:sync-in')->assertSuccessful();

        $this->assertEquals(20000, $asset->fresh()->current_meter);
        $log = FieldSyncLog::first();
        $this->assertSame('needs_review', $log->status);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->getJson('/api/v1/fleet/inbox')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/fleet/inbox/{$log->id}/resolve", ['action' => 'apply_meter'])->assertOk();
        $this->assertEquals(2000, $asset->fresh()->current_meter);
        $this->getJson('/api/v1/fleet/inbox')->assertJsonCount(0, 'data');
    }

    public function test_hostile_and_malformed_submissions_are_rejected_not_applied(): void
    {
        $retired = $this->asset(['status' => 'retired']);
        $this->asset(['asset_number' => 'FA-0002', 'name' => 'B']);

        $this->submit('meter', 'NOPE-1', ['reading' => 5]);                               // unknown asset
        $this->submit('meter', 'FA-0001', ['reading' => 5]);                              // retired
        $this->submit('meter', 'FA-0002', ['reading' => 'abc']);                          // bad number
        $this->submit('inspection', 'FA-0002', ['items' => [['label' => str_repeat('x', 999), 'result' => 'pass']]]);
        $this->submit('inspection', 'FA-0002', ['items' => [['label' => 'Brakes', 'result' => 'maybe']]]);
        $this->submit('explode', 'FA-0002', []);                                          // unknown kind
        DB::connection('fieldsync')->table('fa_submissions')->insert([
            'submission_uuid' => 'bad-json-0001', 'kind' => 'meter', 'asset_number' => 'FA-0002', 'payload' => '{not json',
        ]);
        $this->submit('defect', 'FA-0002', ['title' => '<script>alert(1)</script>'], ['uuid' => 'x']); // bad uuid

        $this->artisan('fleet:sync-in')->assertSuccessful();

        $this->assertSame(8, FieldSyncLog::where('status', 'rejected')->count());
        $this->assertSame(0, FieldAssetInspection::count());
        $this->assertSame(0, FieldAssetWorkOrder::count());
        $this->assertSame(0, $retired->meterReadings()->count());
    }

    public function test_spoofed_photo_types_are_not_stored(): void
    {
        $this->asset();
        $uuid = $this->submit('defect', 'FA-0001', ['title' => 'Dent']);
        $this->photo($uuid, base64_encode('<?php system($_GET["c"]); ?>'), 'shell.jpg');
        $this->photo($uuid, 'not-base64!!', 'bad.png');
        $this->photo($uuid, self::PNG, 'good.png');

        $this->artisan('fleet:sync-in')->assertSuccessful();

        $this->assertSame(1, FieldAssetDocument::count());
        $this->assertSame('image/png', FieldAssetDocument::first()->file_mime);
        $this->assertStringEndsWith('.png', FieldAssetDocument::first()->file_path);
    }

    public function test_absurd_device_clock_is_clamped_to_now(): void
    {
        $asset = $this->asset();
        $this->submit('inspection', 'FA-0001', ['items' => [['label' => 'Lock / latch', 'result' => 'pass']]], ['at' => '2031-01-01 00:00:00']);

        $this->artisan('fleet:sync-in')->assertSuccessful();

        $this->assertTrue(FieldAssetInspection::first()->performed_at->isToday());
    }

    public function test_unreachable_field_database_fails_gracefully(): void
    {
        $this->asset();
        config(['database.connections.fieldsync.database' => '/nonexistent/dir/field.sqlite']);
        DB::purge('fieldsync');

        $this->artisan('fleet:sync-in')->assertFailed();
        $this->artisan('fleet:sync-out')->assertFailed();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->getJson('/api/v1/fleet/dashboard')->assertOk()->assertJsonPath('sync.stale', true);
    }

    // ---- Mobile-app logins (contract v2) ----------------------------------------------------

    private function remoteUsers()
    {
        return DB::connection('fieldsync')->table('fa_users')->get()->keyBy('forgedesk_user_id');
    }

    private function mobileUser(array $attrs = []): User
    {
        static $n = 0;
        $n++;

        return User::factory()->create(array_merge([
            'first_name' => 'Crew', 'last_name' => "Member{$n}", 'name' => "Crew Member{$n}",
            'email' => "Crew{$n}@Example.com", 'role' => 'viewer', 'is_active' => true,
            'mobile_app_access' => true, 'must_change_password' => false, 'password' => 'a-real-password-1',
        ], $attrs));
    }

    public function test_only_users_with_mobile_access_are_published_with_their_forgedesk_password_hash(): void
    {
        $crew = $this->mobileUser();
        $office = $this->mobileUser(['mobile_app_access' => false, 'email' => 'office@example.com']);

        $this->artisan('fleet:sync-out')->assertSuccessful();

        $remote = $this->remoteUsers();
        $this->assertCount(1, $remote, 'users without the flag are never sent');
        $this->assertFalse($remote->has($office->id));
        $row = $remote[$crew->id];
        $this->assertSame(strtolower($crew->email), $row->login, 'login is the lowercased email');
        $this->assertSame($crew->name, $row->display_name);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('a-real-password-1', $row->password_hash), 'same password works');
        $this->assertEquals(1, $row->is_active);
    }

    public function test_accounts_that_cannot_sign_in_get_no_credential_on_the_field_side(): void
    {
        $inactive = $this->mobileUser(['is_active' => false]);
        $disabled = $this->mobileUser(['is_disabled' => true, 'is_active' => false]);
        $temp = $this->mobileUser(['must_change_password' => true]);

        $this->artisan('fleet:sync-out')->assertSuccessful();

        foreach ([$inactive, $disabled, $temp] as $u) {
            $row = $this->remoteUsers()[$u->id];
            $this->assertSame('', $row->password_hash, "{$u->email} must not have a hash on the public server");
            $this->assertEquals(0, $row->is_active);
        }
    }

    public function test_revoking_access_or_deleting_a_user_strips_the_hash_on_the_next_sync(): void
    {
        $revoked = $this->mobileUser();
        $deleted = $this->mobileUser();
        $this->artisan('fleet:sync-out')->assertSuccessful();
        $this->assertNotSame('', $this->remoteUsers()[$revoked->id]->password_hash);

        $revoked->update(['mobile_app_access' => false]);
        $deleted->delete();
        $this->artisan('fleet:sync-out')->assertSuccessful();

        foreach ([$revoked, $deleted] as $u) {
            $row = $this->remoteUsers()[$u->id];
            $this->assertSame('', $row->password_hash);
            $this->assertEquals(0, $row->is_active);
        }
    }

    public function test_a_password_change_in_forgedesk_reaches_the_field_side(): void
    {
        $crew = $this->mobileUser();
        $this->artisan('fleet:sync-out')->assertSuccessful();
        $before = $this->remoteUsers()[$crew->id]->password_hash;

        $crew->update(['password' => 'a-brand-new-password-2']);
        $this->artisan('fleet:sync-out')->assertSuccessful();

        $after = $this->remoteUsers()[$crew->id]->password_hash;
        $this->assertNotSame($before, $after);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('a-brand-new-password-2', $after));
    }
}
