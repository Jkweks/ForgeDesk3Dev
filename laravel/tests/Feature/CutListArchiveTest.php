<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\CutFlow\CutJob;
use App\Models\FdWorkOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CutListArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::connection('cutflow')->hasTable('cut_log_entries')) {
            $this->artisan('migrate', ['--database' => 'cutflow', '--path' => 'database/migrations/cutflow', '--force' => true]);
        }
    }

    public function test_cut_lists_of_complete_work_orders_are_archived(): void
    {
        $user = User::factory()->create();
        $this->withoutMiddleware(\App\Http\Middleware\CheckPermission::class);
        $job = BusinessJob::create(['job_number' => '1001', 'job_name' => 'Tower']);
        $open = FdWorkOrder::create(['business_job_id' => $job->id, 'release_number' => 1]);
        $done = FdWorkOrder::create(['business_job_id' => $job->id, 'release_number' => 2]);
        $done->forceFill(['status' => 'complete'])->save();
        CutJob::create(['name' => 'open-wo', 'work_order_id' => $open->id]);
        CutJob::create(['name' => 'done-wo', 'work_order_id' => $done->id]);
        CutJob::create(['name' => 'standalone']);

        $names = fn ($r) => collect($r->json('cut_lists'))->pluck('name')->sort()->values()->all();

        $r = $this->actingAs($user)->getJson('/api/v1/cut-lists')->assertOk();
        $this->assertSame(['open-wo', 'standalone'], $names($r));
        $this->assertSame(1, $r->json('archived_count'));

        $r = $this->actingAs($user)->getJson('/api/v1/cut-lists?archived=1')->assertOk();
        $this->assertSame(['done-wo'], $names($r));

        // A list on a complete work order that still has pieces to cut is not archived.
        $addOn = CutJob::create(['name' => 'add-on', 'work_order_id' => $done->id, 'sequence' => 2]);
        \App\Models\CutFlow\Part::create(['cut_job_id' => $addOn->id, 'name' => 'E1', 'dimension_inches' => 10, 'qty_original' => 2, 'qty_remaining' => 2]);
        $r = $this->actingAs($user)->getJson('/api/v1/cut-lists?archived=1');
        $this->assertSame(['done-wo'], $names($r));
        $r = $this->actingAs($user)->getJson('/api/v1/cut-lists');
        $this->assertContains('add-on', $names($r));

        // A work order's own tab lists all of its lists.
        $r = $this->actingAs($user)->getJson("/api/v1/cut-lists?work_order_id={$done->id}");
        $this->assertSame(['add-on', 'done-wo'], $names($r));
        $addOn->delete();

        // Reopening the work order un-archives its list.
        $done->forceFill(['status' => 'active'])->save();
        $r = $this->actingAs($user)->getJson('/api/v1/cut-lists?archived=1');
        $this->assertSame([], $names($r));
    }
}
