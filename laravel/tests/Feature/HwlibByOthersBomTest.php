<?php

namespace Tests\Feature;

use App\Models\ConfiguratorHwlibItem;
use App\Models\ConfiguratorHwlibLink;
use App\Models\DoorFrameConfiguration;
use App\Models\DoorFrameOpeningSpec;
use App\Services\Configurator\HwlibBomGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A "by others" hardware item (the ASA strike) is listed but must not be tied to a stock product
 * (so nothing is reserved for it), while its backers still are.
 */
class HwlibByOthersBomTest extends TestCase
{
    public function test_by_others_item_has_no_product_but_keeps_its_backers(): void
    {
        Schema::create('configurator_hwlib_items', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('manufacturer')->nullable();
            $t->string('model_number')->nullable();
            $t->string('pn')->nullable();
            $t->boolean('handed')->default(false);
            $t->boolean('by_others')->default(false);
            $t->unsignedBigInteger('default_strike_item_id')->nullable();
            $t->unsignedBigInteger('default_cover_item_id')->nullable();
            $t->timestamps();
        });
        Schema::create('configurator_hwlib_item_backers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('item_id');
            $t->string('series')->nullable();
            $t->string('side')->nullable();
            $t->string('pn')->nullable();
            $t->string('description')->nullable();
            $t->decimal('qty', 8, 3)->default(1);
            $t->unsignedBigInteger('backer_id')->nullable();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('part_number')->nullable();
            $t->string('finish')->nullable();
            $t->softDeletes();
        });

        DB::table('products')->insert([['part_number' => 'ASA'], ['part_number' => 'P2119']]);
        $item = ConfiguratorHwlibItem::create(['name' => 'ASA STRIKE', 'pn' => 'ASA', 'by_others' => true]);
        DB::table('configurator_hwlib_item_backers')->insert([
            'item_id' => $item->id, 'series' => 'Standard', 'side' => 'frame', 'pn' => 'P2119', 'description' => 'Backer for ASA Strike', 'qty' => 2,
        ]);

        $link = new ConfiguratorHwlibLink(['item_id' => $item->id, 'quantity' => 1, 'series' => 'Standard', 'leaf' => 'both']);
        $link->id = null;
        $link->setRelation('item', $item);
        $config = new DoorFrameConfiguration(['job_scope' => 'door_and_frame', 'quantity' => 1]);
        $config->setRelation('hardwareLinks', new \Illuminate\Database\Eloquent\Collection([$link]));
        $config->setRelation('openingSpecs', new DoorFrameOpeningSpec(['opening_type' => 'single', 'hand_single' => 'lh', 'finish' => 'C2']));

        $rows = collect((new HwlibBomGenerator)->generate($config)['rows']);

        $strike = $rows->firstWhere('source_type', 'item');
        $this->assertNull($strike['product_id']);
        $this->assertSame('ASA STRIKE (by others)', $strike['part_label']);

        $backer = $rows->firstWhere('source_type', 'backer');
        $this->assertNotNull($backer['product_id']);
        $this->assertEquals(2, $backer['quantity']);
    }
}
