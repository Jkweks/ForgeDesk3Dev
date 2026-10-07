<?php

namespace Tests\Feature;

use App\Models\ConfiguratorHwlibItem;
use App\Models\ConfiguratorHwlibLink;
use App\Models\DoorFrameConfiguration;
use App\Models\DoorFrameOpeningSpec;
use App\Services\Configurator\HwlibBomGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A lock's default strike/cover is auto-linked when the lock is added, but configurations linked
 * before that wiring (or whose default link was removed) must still get them in the BOM.
 *
 * Builds only the few tables the generator reads rather than using RefreshDatabase: the full
 * migration set doesn't run on sqlite (e.g. the inventory_commitments view).
 */
class HwlibDefaultStrikeCoverBomTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('configurator_hwlib_items', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('manufacturer')->nullable();
            $t->string('model_number')->nullable();
            $t->string('pn')->nullable();
            $t->boolean('handed')->default(false);
            $t->unsignedBigInteger('default_strike_item_id')->nullable();
            $t->unsignedBigInteger('default_cover_item_id')->nullable();
            $t->timestamps();
        });
        Schema::create('configurator_hwlib_item_backers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('item_id');
            $t->string('series')->nullable();
            $t->string('side')->nullable();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('part_number')->nullable();
            $t->string('finish')->nullable();
            $t->softDeletes();
        });
    }

    private function bomLabels(array $linkedItems): array
    {
        $links = (new \Illuminate\Database\Eloquent\Collection($linkedItems))->map(function (ConfiguratorHwlibItem $item) {
            $link = new ConfiguratorHwlibLink(['item_id' => $item->id, 'quantity' => 1, 'series' => 'Standard', 'leaf' => 'both']);
            $link->id = null;
            $link->setRelation('item', $item);

            return $link;
        });

        $config = new DoorFrameConfiguration(['job_scope' => 'door_and_frame', 'quantity' => 1]);
        $config->setRelation('hardwareLinks', $links);
        $config->setRelation('openingSpecs', new DoorFrameOpeningSpec(['opening_type' => 'single', 'hand_single' => 'lh', 'finish' => 'C2']));

        return collect((new HwlibBomGenerator)->generate($config)['rows'])->pluck('part_label')->all();
    }

    private function item(string $name, array $extra = []): ConfiguratorHwlibItem
    {
        return ConfiguratorHwlibItem::create(['name' => $name] + $extra);
    }

    public function test_missing_default_strike_and_cover_are_added_to_the_bom(): void
    {
        $strike = $this->item('Test Strike');
        $cover = $this->item('Test Cover');
        $lock = $this->item('Test Lock', ['default_strike_item_id' => $strike->id, 'default_cover_item_id' => $cover->id]);

        $labels = $this->bomLabels([$lock]);

        $this->assertContains('Test Lock', $labels);
        $this->assertContains('Test Strike', $labels);
        $this->assertContains('Test Cover', $labels);
    }

    public function test_already_linked_defaults_are_not_duplicated(): void
    {
        $strike = $this->item('Test Strike');
        $cover = $this->item('Test Cover');
        $lock = $this->item('Test Lock', ['default_strike_item_id' => $strike->id, 'default_cover_item_id' => $cover->id]);

        $labels = $this->bomLabels([$lock, $strike, $cover]);

        $this->assertSame(1, count(array_keys($labels, 'Test Strike')));
        $this->assertSame(1, count(array_keys($labels, 'Test Cover')));
    }

    public function test_two_locks_sharing_a_default_strike_add_it_once(): void
    {
        $strike = $this->item('Shared Strike');
        $a = $this->item('Lock A', ['default_strike_item_id' => $strike->id]);
        $b = $this->item('Lock B', ['default_strike_item_id' => $strike->id]);

        $this->assertSame(1, count(array_keys($this->bomLabels([$a, $b]), 'Shared Strike')));
    }
}
