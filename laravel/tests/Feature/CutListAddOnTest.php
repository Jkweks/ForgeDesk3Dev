<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\CutFlow\CutJob;
use App\Models\CutFlow\Part;
use App\Models\DoorFrameConfiguration;
use App\Models\DoorFrameFrameConfig;
use App\Models\DoorFrameFramePart;
use App\Models\FdWorkOrder;
use App\Models\Product;
use App\Services\Configurator\CutFlowExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CutListAddOnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::connection('cutflow')->hasTable('cut_log_entries')) {
            $this->artisan('migrate', ['--database' => 'cutflow', '--path' => 'database/migrations/cutflow', '--force' => true]);
        }
    }

    private function opening(FdWorkOrder $wo, BusinessJob $job, string $label, float $len): DoorFrameConfiguration
    {
        static $n = 0;
        $n++;
        $product = Product::firstOrCreate(['sku' => 'EXT-1'], ['supplier_id' => \App\Models\Supplier::firstOrCreate(['name' => 'S'])->id, 'description' => 'Ext', 'part_number' => 'E100', 'finish' => '0R']);
        $cfg = DoorFrameConfiguration::create([
            'business_job_id' => $job->id, 'work_order_id' => $wo->id, 'job_scope' => 'door_and_frame',
            'status' => 'released', 'quantity' => 1, 'cutflow_include_frame' => true, 'cutflow_include_door' => true,
        ]);
        $fc = DoorFrameFrameConfig::create(['configuration_id' => $cfg->id]);
        DoorFrameFramePart::create([
            'frame_config_id' => $fc->id, 'part_label' => $label, 'product_id' => $product->id,
            'calculated_length' => $len, 'quantity' => 2, 'unit_type' => 'length', 'source_type' => 'auto',
        ]);

        return $cfg;
    }

    public function test_release_after_completion_starts_an_add_on_list_and_leaves_the_finished_one_alone(): void
    {
        $job = BusinessJob::create(['job_number' => '2001', 'job_name' => 'Plaza', 'status' => 'active']);
        $wo = FdWorkOrder::create(['business_job_id' => $job->id, 'release_number' => 1]);
        $svc = app(CutFlowExportService::class);

        $first = $this->opening($wo, $job, 'Head', 36);
        $svc->exportWorkOrder($wo, $first);
        $main = CutJob::where('work_order_id', $wo->id)->firstOrFail();
        $this->assertSame(1, $main->sequence);
        Part::where('cut_job_id', $main->id)->update(['qty_remaining' => 0]); // fully cut
        $mainParts = Part::where('cut_job_id', $main->id)->count();

        $wo->forceFill(['status' => 'complete'])->save();

        $second = $this->opening($wo, $job, 'Jamb', 84);
        $result = $svc->exportWorkOrder($wo->fresh(), $second);
        $this->assertTrue($result['sent']);

        $this->assertSame(2, CutJob::where('work_order_id', $wo->id)->count());
        $addOn = CutJob::where('work_order_id', $wo->id)->where('sequence', 2)->firstOrFail();
        $this->assertStringContainsString('Add-on 1', $addOn->name);
        $this->assertSame(2, $second->fresh()->cutflow_supplement);
        $this->assertSame($mainParts, Part::where('cut_job_id', $main->id)->count(), 'finished list untouched');
        $this->assertSame(0, (int) Part::where('cut_job_id', $main->id)->sum('qty_remaining'));
        $this->assertSame(1, Part::where('cut_job_id', $addOn->id)->count());
        $this->assertSame(2, (int) Part::where('cut_job_id', $addOn->id)->sum('qty_remaining'));

        // Re-releasing the same opening feeds the same add-on list (no third list, no duplicate lines).
        $svc->exportWorkOrder($wo->fresh(), $second->fresh());
        $this->assertSame(2, CutJob::where('work_order_id', $wo->id)->count());
        $this->assertSame(1, Part::where('cut_job_id', $addOn->id)->count());

        // A later opening on the still-complete work order starts add-on #2.
        $third = $this->opening($wo, $job, 'Sill', 40);
        $svc->exportWorkOrder($wo->fresh(), $third);
        $this->assertSame(3, $third->fresh()->cutflow_supplement);
    }

    public function test_open_work_order_keeps_merging_into_its_main_list(): void
    {
        $job = BusinessJob::create(['job_number' => '2002', 'job_name' => 'Hall', 'status' => 'active']);
        $wo = FdWorkOrder::create(['business_job_id' => $job->id, 'release_number' => 1]);
        $svc = app(CutFlowExportService::class);

        $svc->exportWorkOrder($wo, $this->opening($wo, $job, 'Head', 36));
        $svc->exportWorkOrder($wo, $this->opening($wo, $job, 'Jamb', 84));

        $this->assertSame(1, CutJob::where('work_order_id', $wo->id)->count());
        $this->assertSame(2, Part::count());
    }
}
