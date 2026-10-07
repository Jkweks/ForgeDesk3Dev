<?php

namespace Tests\Feature;

use App\Models\ConfiguratorHwlibBacker;
use App\Models\ConfiguratorHwlibBackerFastener;
use App\Models\ConfiguratorHwlibCategory;
use App\Models\ConfiguratorHwlibFastener;
use App\Models\ConfiguratorHwlibItem;
use App\Models\ConfiguratorHwlibItemBacker;
use App\Models\ConfiguratorHwlibLink;
use App\Models\DoorFrameConfiguration;
use App\Models\DoorFrameOpeningSpec;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Configurator\HwlibBomGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Hardware BOM is shown in Standard and Custom sections, split by the link each row came from.
 * Backers, fasteners and default covers/strikes must carry their hardware's link so they stay with it.
 */
class HwlibBomAttributionTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $pn): Product
    {
        $supplier = Supplier::firstOrCreate(['name' => 'Attribution Supplier']);
        $product = Product::create(['sku' => "SKU-{$pn}", 'description' => "Part {$pn}", 'supplier_id' => $supplier->id]);
        Product::where('id', $product->id)->update(['part_number' => $pn, 'finish' => 'C2']);

        return $product;
    }

    private function generate(array $linked): array
    {
        $links = (new Collection($linked))->map(function (array $entry) {
            [$id, $item] = $entry;
            $link = new ConfiguratorHwlibLink(['item_id' => $item->id, 'quantity' => 1, 'series' => 'Standard', 'leaf' => 'both']);
            $link->id = $id;
            $link->setRelation('item', $item);

            return $link;
        });

        $config = new DoorFrameConfiguration(['job_scope' => 'door_and_frame', 'quantity' => 1]);
        $config->setRelation('hardwareLinks', $links);
        $config->setRelation('openingSpecs', new DoorFrameOpeningSpec(['opening_type' => 'single', 'hand_single' => 'lhr', 'finish' => 'C2']));

        return (new HwlibBomGenerator)->generate($config)['rows'];
    }

    public function test_item_backer_fastener_and_default_cover_rows_all_carry_the_hardware_they_belong_to(): void
    {
        foreach (['P-LOCK', 'P-COV', 'P-BKR', 'P-FST', 'P-BKR2'] as $pn) {
            $this->product($pn);
        }
        $category = ConfiguratorHwlibCategory::create(['name' => 'Locks']);
        $cover = ConfiguratorHwlibItem::create(['category_id' => $category->id, 'name' => 'Lock Cover', 'pn' => 'P-COV']);
        $lock = ConfiguratorHwlibItem::create(['category_id' => $category->id, 'name' => 'Std Lock', 'pn' => 'P-LOCK', 'vos_standard' => true, 'default_cover_item_id' => $cover->id]);
        $custom = ConfiguratorHwlibItem::create(['category_id' => $category->id, 'name' => 'Custom Closer', 'pn' => null]);

        $backer = ConfiguratorHwlibBacker::create(['pn' => 'P-BKR', 'description' => 'Lock backer']);
        $fastener = ConfiguratorHwlibFastener::create(['pn' => 'P-FST', 'description' => 'Backer screw']);
        ConfiguratorHwlibBackerFastener::create(['backer_id' => $backer->id, 'fastener_id' => $fastener->id, 'qty' => 2]);
        ConfiguratorHwlibItemBacker::create(['item_id' => $lock->id, 'side' => 'door', 'series' => 'Standard', 'qty' => 1, 'backer_id' => $backer->id]);
        ConfiguratorHwlibItemBacker::create(['item_id' => $custom->id, 'side' => 'door', 'series' => 'Standard', 'qty' => 1, 'pn' => 'P-BKR2', 'description' => 'Closer backer']);

        // Only the lock (link 11) and the custom item (link 12) are linked; the lock's cover is not,
        // so the generator adds it, and it must be attributed to the lock.
        $rows = collect($this->generate([[11, $lock], [12, $custom]]));
        $byLabel = $rows->keyBy('part_label');

        $this->assertSame(11, $byLabel['Std Lock']['hwlib_link_id']);
        $this->assertSame(11, $byLabel['Lock Cover']['hwlib_link_id'], 'a default cover belongs with the lock it accompanies');
        $this->assertSame(11, $byLabel['Lock backer']['hwlib_link_id']);
        $this->assertSame('backer', $byLabel['Lock backer']['source_type']);
        $this->assertSame(11, $byLabel['Backer screw']['hwlib_link_id']);
        $this->assertSame('fastener', $byLabel['Backer screw']['source_type']);
        $this->assertSame(12, $byLabel['Custom Closer']['hwlib_link_id']);
        $this->assertSame(12, $byLabel['Closer backer']['hwlib_link_id']);

        $this->assertSame([], $rows->whereNull('hwlib_link_id')->pluck('part_label')->all(), 'no generated row is left unattributed');
    }
}
