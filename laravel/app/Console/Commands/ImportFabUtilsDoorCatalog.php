<?php

namespace App\Console\Commands;

use App\Models\ConfiguratorDoorType;
use App\Models\ConfiguratorGlassSpec;
use App\Models\ConfiguratorMidLug;
use App\Models\ConfiguratorRail;
use App\Models\ConfiguratorRailLug;
use App\Models\ConfiguratorSettingBlockKit;
use App\Models\ConfiguratorTieRod;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time / re-runnable snapshot import of the door catalog (door_types,
 * rails, rail_lugs, mid_lugs, glass_specs, setting_block_kits, tie_rods) from
 * the standalone fab_utils configurator database, dumped to JSON files under
 * storage/app/fab_utils_import.
 *
 * Unlike the frame catalog, fab_utils has no PN->description lookup table
 * covering these door hardware PNs at all (checked: zero matches in its own
 * `products` table), so placeholder products get a synthesized description
 * based on which catalog column they came from. Per instruction, all created
 * placeholders are assigned supplier_id 1.
 *
 * Full replace: existing configurator_door_* rows are wiped and reseeded from
 * the dump each run.
 */
class ImportFabUtilsDoorCatalog extends Command
{
    protected $signature = 'configurator:import-fab-utils-doors {--path=fab_utils_import : Directory under storage/app holding the exported JSON files}';

    protected $description = 'Import the fab_utils door catalog (door types/rails/lugs/glass specs/setting block kits/tie rods) into the configurator catalog tables';

    private const PLACEHOLDER_SUPPLIER_ID = 1;

    private array $productCache = [];

    private int $createdCount = 0;

    public function handle(): int
    {
        $dir = storage_path('app/'.$this->option('path'));

        if (! is_dir($dir)) {
            $this->error("Import directory not found: {$dir}");

            return self::FAILURE;
        }

        $doorTypes = $this->readJson($dir.'/door_types.json');
        $rails = $this->readJson($dir.'/rails.json');
        $railLugs = $this->readJson($dir.'/rail_lugs.json');
        $midLugs = $this->readJson($dir.'/mid_lugs.json');
        $glassSpecs = $this->readJson($dir.'/glass_specs.json');
        $sbks = $this->readJson($dir.'/setting_block_kits.json');
        $tieRods = $this->readJson($dir.'/tie_rods.json');

        $this->info(sprintf(
            'Loaded %d door types, %d rails, %d rail lugs, %d mid lugs, %d glass specs, %d setting block kits, %d tie rods.',
            count($doorTypes), count($rails), count($railLugs), count($midLugs), count($glassSpecs), count($sbks), count($tieRods)
        ));

        DB::transaction(function () use ($doorTypes, $rails, $railLugs, $midLugs, $glassSpecs, $sbks, $tieRods) {
            ConfiguratorDoorType::query()->delete();
            ConfiguratorRail::query()->delete();
            ConfiguratorRailLug::query()->delete();
            ConfiguratorMidLug::query()->delete();
            ConfiguratorGlassSpec::query()->delete();
            ConfiguratorSettingBlockKit::query()->delete();
            ConfiguratorTieRod::query()->delete();

            foreach ($doorTypes as $r) {
                ConfiguratorDoorType::create([
                    'series' => $r['series'],
                    'stile_name' => $r['stile_name'],
                    'stile_height' => $r['stile_height'],
                    'bev_pn' => $this->pn($r['bev_pn'], "Door Stile (Bevel) - {$r['series']} {$r['stile_name']}"),
                    'rab_pn' => $this->pn($r['rab_pn'], "Door Stile (Rabbet/Continuous Hinge) - {$r['series']} {$r['stile_name']}"),
                    'cp_pn' => $this->pn($r['cp_pn'], "Door Stile (Center Pivot) - {$r['series']} {$r['stile_name']}"),
                    'ast_pn' => $this->pn($r['ast_pn'], "Door Astragal Stile - {$r['series']} {$r['stile_name']}"),
                    'inact_pn' => $this->pn($r['inact_pn'], "Door Inactive Meeting Stile - {$r['series']} {$r['stile_name']}"),
                ]);
            }
            $this->info('Imported '.count($doorTypes).' door types.');

            foreach ($rails as $r) {
                ConfiguratorRail::create([
                    'rail_type' => $r['rail_type'],
                    'label' => $r['label'],
                    'std_pn' => $this->pn($r['std_pn'], "Door Rail {$r['label']} - Standard"),
                    'thermal_pn' => $this->pn($r['thermal_pn'], "Door Rail {$r['label']} - Thermal"),
                    'mon_pn' => $this->pn($r['mon_pn'], "Door Rail {$r['label']} - Monumental"),
                    'value_in' => $r['value_in'],
                    'stacked_std_pn' => $this->pn($r['stacked_std_pn'], "Door Rail {$r['label']} - Standard (Stacked)"),
                    'stacked_thermal_pn' => $this->pn($r['stacked_thermal_pn'], "Door Rail {$r['label']} - Thermal (Stacked)"),
                    'stacked_mon_pn' => $this->pn($r['stacked_mon_pn'], "Door Rail {$r['label']} - Monumental (Stacked)"),
                ]);
            }
            $this->info('Imported '.count($rails).' rails.');

            foreach ($railLugs as $r) {
                ConfiguratorRailLug::create([
                    'rail_pn' => $r['rail_pn'],
                    'lug_pn' => $this->pn($r['lug_pn'], "Rail Lug for {$r['rail_pn']}"),
                ]);
            }
            $this->info('Imported '.count($railLugs).' rail lugs.');

            foreach ($midLugs as $r) {
                ConfiguratorMidLug::create([
                    'rail_pn' => $r['rail_pn'],
                    'lug_pn' => $this->pn($r['lug_pn'], "Mid Rail Lug for {$r['rail_pn']}"),
                    'f1_pn' => $this->pn($r['f1_pn'], "Mid Rail Lug Fastener #1 for {$r['rail_pn']}"),
                    'f1_qty' => $r['f1_qty'],
                    'f2_pn' => $this->pn($r['f2_pn'], "Mid Rail Lug Fastener #2 for {$r['rail_pn']}"),
                    'f2_qty' => $r['f2_qty'],
                ]);
            }
            $this->info('Imported '.count($midLugs).' mid lugs.');

            foreach ($glassSpecs as $r) {
                ConfiguratorGlassSpec::create([
                    'thickness' => $r['thickness'],
                    'stop_pn' => $this->pn($r['stop_pn'], "Glass Stop - {$r['thickness']}"),
                    'gasket_pn' => $this->pn($r['gasket_pn'], "Glass Gasket - {$r['thickness']}"),
                    'gasket2_pn' => $this->pn($r['gasket2_pn'], "Glass Gasket #2 - {$r['thickness']}"),
                    'gasket_qty_factor' => $r['gasket_qty_factor'],
                    'stop_height' => $r['stop_height'],
                ]);
            }
            $this->info('Imported '.count($glassSpecs).' glass specs.');

            foreach ($sbks as $r) {
                ConfiguratorSettingBlockKit::create([
                    'series' => $r['series'],
                    'glass_thickness' => $r['glass_thickness'],
                    'kit1_pn' => $this->pn($r['kit1_pn'], "Setting Block Kit - {$r['series']} {$r['glass_thickness']}"),
                    'kit2_pn' => $this->pn($r['kit2_pn'], "Setting Block Kit #2 - {$r['series']} {$r['glass_thickness']}"),
                ]);
            }
            $this->info('Imported '.count($sbks).' setting block kits.');

            foreach ($tieRods as $r) {
                ConfiguratorTieRod::create([
                    'pn' => $this->pn($r['pn'], "Tie Rod - {$r['series']} (".$r['min_len'].'"-'.$r['max_len'].'")'),
                    'min_len' => $r['min_len'],
                    'max_len' => $r['max_len'],
                    'series' => $r['series'],
                    'mid_val' => $r['mid_val'],
                ]);
            }
            $this->info('Imported '.count($tieRods).' tie rods.');
        });

        $this->info("Created {$this->createdCount} new placeholder product(s) for door catalog PNs not already in inventory.");
        $this->info('Import complete.');

        return self::SUCCESS;
    }

    private function readJson(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?? [];
    }

    /**
     * Ensure a Product exists for this PN (matching on part_number, and finish
     * when the PN carries a baked-in "-BL"/"-C2"/"-DB"/"-0R" suffix), creating
     * a placeholder if needed. Returns the PN unchanged (catalog tables store
     * PN strings, not product_id) — this only guarantees resolvability later.
     */
    private function pn(?string $rawPn, string $description): ?string
    {
        if (! $rawPn) {
            return null;
        }

        if (isset($this->productCache[$rawPn])) {
            return $rawPn;
        }

        $base = $rawPn;
        $finish = null;
        if (preg_match('/^(.*)-(BL|C2|DB|0R)$/', $rawPn, $m)) {
            [$base, $finish] = [$m[1], $m[2]];
        }

        $query = Product::withTrashed()->where('part_number', $base);
        if ($finish) {
            $query->where('finish', $finish);
        }
        $existing = $query->first() ?? Product::withTrashed()->where('part_number', $base)->first();

        if (! $existing) {
            Product::create([
                'sku' => Product::generateSku($base, $finish),
                'part_number' => $base,
                'finish' => $finish,
                'description' => $description,
                'unit_cost' => 0,
                'quantity_on_hand' => 0,
                'quantity_committed' => 0,
                'supplier_id' => self::PLACEHOLDER_SUPPLIER_ID,
                'is_special_order' => true,
                'nonsof' => true,
            ]);
            $this->createdCount++;
        }

        $this->productCache[$rawPn] = true;

        return $rawPn;
    }
}
