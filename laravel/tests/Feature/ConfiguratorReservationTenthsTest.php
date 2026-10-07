<?php

namespace Tests\Feature;

use App\Services\Configurator\ConfigurationReservationBridge;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Extrusions reserve as fractions of a stick to the next 1/10 (the old configurator's precision),
 * not whole sticks. Only the tables the bridge reads are built: the full migration set doesn't
 * run on sqlite.
 */
class ConfiguratorReservationTenthsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('part_number')->nullable();
            $t->boolean('is_length_based')->default(false);
            $t->decimal('configurator_length', 10, 2)->nullable();
            $t->softDeletes();
        });
        DB::table('products')->insert([
            ['id' => 1, 'part_number' => 'E1000', 'is_length_based' => false, 'configurator_length' => 100],
            ['id' => 2, 'part_number' => 'G1', 'is_length_based' => true, 'configurator_length' => 100],
        ]);
    }

    private function part(int $pid, float $length, int $qty = 1, string $source = 'extrusion'): object
    {
        return (object) [
            'product_id' => $pid, 'product' => \App\Models\Product::find($pid), 'unit_type' => 'length',
            'calculated_length' => $length, 'quantity' => $qty, 'source_type' => $source,
        ];
    }

    private function desired(array $parts): array
    {
        $config = new \App\Models\DoorFrameConfiguration;
        $config->setRelation('frameConfig', null);
        $config->setRelation('doorConfigs', collect());
        $config->setRelation('hardwareParts', collect($parts));

        return (new ConfigurationReservationBridge)->desiredQuantities(collect([$config]))->all();
    }

    public function test_extrusion_reserves_a_tenth_of_a_stick_not_a_whole_one(): void
    {
        $this->assertEqualsWithDelta(0.3, $this->desired([$this->part(1, 28.5)])[1], 0.0001);
    }

    public function test_job_total_rounds_up_once_not_per_line(): void
    {
        // 4 x 11" = 44" of 100" -> 0.5 (per-line rounding would give 4 x 0.2 = 0.8)
        $this->assertEqualsWithDelta(0.5, $this->desired([$this->part(1, 11, 4)])[1], 0.0001);
    }

    public function test_exact_tenths_do_not_round_up_an_extra_step(): void
    {
        $this->assertEqualsWithDelta(0.5, $this->desired([$this->part(1, 25, 2)])[1], 0.0001);
    }

    public function test_cut_longer_than_a_stick_costs_whole_sticks(): void
    {
        $this->assertEqualsWithDelta(2.0, $this->desired([$this->part(1, 130)])[1], 0.0001);
    }

    public function test_roll_stock_still_reserves_to_a_tenth_of_a_roll(): void
    {
        $this->assertEqualsWithDelta(0.3, $this->desired([$this->part(2, 28.5, 1, 'hardware')])[2], 0.0001);
    }
}
