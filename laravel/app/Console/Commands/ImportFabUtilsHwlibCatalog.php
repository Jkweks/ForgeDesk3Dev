<?php

namespace App\Console\Commands;

use App\Models\ConfiguratorHwlibBacker;
use App\Models\ConfiguratorHwlibBackerFastener;
use App\Models\ConfiguratorHwlibCategory;
use App\Models\ConfiguratorHwlibCategoryVariable;
use App\Models\ConfiguratorHwlibFastener;
use App\Models\ConfiguratorHwlibItem;
use App\Models\ConfiguratorHwlibItemBacker;
use App\Models\ConfiguratorHwlibItemValue;
use App\Models\ConfiguratorHwlibVariable;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time / re-runnable snapshot import of the hardware library (hwlib_*)
 * from the standalone fab_utils configurator database, dumped to JSON files
 * under storage/app/fab_utils_import. Does NOT import hwlib_saved_config_links
 * — those are fab_utils' own historical per-job hardware selections, not
 * reusable catalog data (same reasoning as skipping frame/door saved_configs).
 *
 * Full replace: existing configurator_hwlib_* catalog rows are wiped and
 * reseeded from the dump each run.
 */
class ImportFabUtilsHwlibCatalog extends Command
{
    protected $signature = 'configurator:import-fab-utils-hwlib {--path=fab_utils_import : Directory under storage/app holding the exported JSON files}';

    protected $description = 'Import the fab_utils hardware library (variables/categories/items/backers/fasteners) into the configurator catalog tables';

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

        $variables = $this->readJson($dir.'/hwlib_variables.json');
        $categories = $this->readJson($dir.'/hwlib_categories.json');
        $categoryVariables = $this->readJson($dir.'/hwlib_category_variables.json');
        $items = $this->readJson($dir.'/hwlib_items.json');
        $itemValues = $this->readJson($dir.'/hwlib_item_values.json');
        $fasteners = $this->readJson($dir.'/hwlib_fasteners.json');
        $backers = $this->readJson($dir.'/hwlib_backers.json');
        $backerFasteners = $this->readJson($dir.'/hwlib_backer_fasteners.json');
        $itemBackers = $this->readJson($dir.'/hwlib_item_backers.json');

        $this->info(sprintf(
            'Loaded %d variables, %d categories, %d category-variable links, %d items, %d item values, %d fasteners, %d backers, %d backer-fasteners, %d item-backers.',
            count($variables), count($categories), count($categoryVariables), count($items), count($itemValues),
            count($fasteners), count($backers), count($backerFasteners), count($itemBackers)
        ));

        DB::transaction(function () use ($variables, $categories, $categoryVariables, $items, $itemValues, $fasteners, $backers, $backerFasteners, $itemBackers) {
            // Full replace — wipe previously imported catalog rows before reseeding.
            // Deleting variables cascades category_variables/item_values/link_values;
            // deleting categories cascades to items -> item_values/item_backers etc.
            ConfiguratorHwlibCategory::query()->delete();
            ConfiguratorHwlibVariable::query()->delete();
            ConfiguratorHwlibBacker::query()->delete();
            ConfiguratorHwlibFastener::query()->delete();

            $variableMap = [];
            foreach ($variables as $v) {
                $row = ConfiguratorHwlibVariable::create([
                    'code' => $v['code'], 'label' => $v['label'], 'group_name' => $v['group_name'],
                    'var_type' => $v['var_type'], 'unit' => $v['unit'], 'options' => $v['options'] ?? [],
                    'is_calculated' => $v['is_calculated'], 'formula' => $v['formula'],
                    'default_value' => $v['default_value'], 'notes' => $v['notes'], 'sort_order' => $v['sort_order'] ?? 0,
                    'side' => $v['side'], 'show_in_report' => $v['show_in_report'] ?? true, 'is_inspection' => $v['is_inspection'] ?? false,
                ]);
                $variableMap[$v['id']] = $row->id;
            }
            // Second pass for overrides_variable_id self-references.
            foreach ($variables as $v) {
                if ($v['overrides_variable_id'] ?? null) {
                    ConfiguratorHwlibVariable::where('id', $variableMap[$v['id']])
                        ->update(['overrides_variable_id' => $variableMap[$v['overrides_variable_id']] ?? null]);
                }
            }
            $this->info('Imported '.count($variableMap).' variables.');

            $categoryMap = [];
            foreach ($categories as $c) {
                $row = ConfiguratorHwlibCategory::create([
                    'name' => $c['name'], 'description' => $c['description'], 'sort_order' => $c['sort_order'] ?? 0,
                ]);
                $categoryMap[$c['id']] = $row->id;
            }
            $this->info('Imported '.count($categoryMap).' categories.');

            foreach ($categoryVariables as $cv) {
                if (! isset($categoryMap[$cv['category_id']], $variableMap[$cv['variable_id']])) {
                    continue;
                }
                ConfiguratorHwlibCategoryVariable::create([
                    'category_id' => $categoryMap[$cv['category_id']],
                    'variable_id' => $variableMap[$cv['variable_id']],
                    'sort_order' => $cv['sort_order'] ?? 0,
                ]);
            }
            $this->info('Imported '.count($categoryVariables).' category-variable links.');

            $itemMap = [];
            $skippedItems = 0;
            foreach ($items as $i) {
                if (! isset($categoryMap[$i['category_id']])) {
                    $skippedItems++;

                    continue;
                }
                $pn = $this->pn($i['pn'] ?: null, $i['name']);
                $row = ConfiguratorHwlibItem::create([
                    'category_id' => $categoryMap[$i['category_id']],
                    'name' => $i['name'], 'manufacturer' => $i['manufacturer'], 'model_number' => $i['model_number'],
                    'pn' => $pn, 'notes' => $i['notes'], 'active' => $i['active'] ?? true,
                    'vos_standard' => $i['vos_standard'] ?? false, 'finishes' => $i['finishes'] ?? [],
                    'min_width' => $i['min_width'], 'max_width' => $i['max_width'],
                    'min_height' => $i['min_height'], 'max_height' => $i['max_height'],
                    'field_install' => $i['field_install'] ?? false, 'handed' => $i['handed'] ?? false,
                ]);
                $itemMap[$i['id']] = $row->id;
            }
            // Second pass for default_strike_item_id / default_cover_item_id self-references.
            foreach ($items as $i) {
                if (! isset($itemMap[$i['id']])) {
                    continue;
                }
                $update = [];
                if ($i['default_strike_item_id'] ?? null) {
                    $update['default_strike_item_id'] = $itemMap[$i['default_strike_item_id']] ?? null;
                }
                if ($i['default_cover_item_id'] ?? null) {
                    $update['default_cover_item_id'] = $itemMap[$i['default_cover_item_id']] ?? null;
                }
                if ($update) {
                    ConfiguratorHwlibItem::where('id', $itemMap[$i['id']])->update($update);
                }
            }
            $this->info('Imported '.count($itemMap)." items ({$skippedItems} skipped — missing category).");

            $importedItemValues = 0;
            foreach ($itemValues as $iv) {
                if (! isset($itemMap[$iv['item_id']], $variableMap[$iv['variable_id']])) {
                    continue;
                }
                ConfiguratorHwlibItemValue::create([
                    'item_id' => $itemMap[$iv['item_id']],
                    'variable_id' => $variableMap[$iv['variable_id']],
                    'value_text' => $iv['value_text'],
                ]);
                $importedItemValues++;
            }
            $this->info("Imported {$importedItemValues} item values.");

            $fastenerMap = [];
            foreach ($fasteners as $f) {
                $row = ConfiguratorHwlibFastener::create([
                    'pn' => $this->pn($f['pn'], $f['description'] ?: $f['pn']),
                    'description' => $f['description'], 'notes' => $f['notes'],
                    'active' => $f['active'] ?? true, 'sort_order' => $f['sort_order'] ?? 0,
                ]);
                $fastenerMap[$f['id']] = $row->id;
            }
            $this->info('Imported '.count($fastenerMap).' fasteners.');

            $backerMap = [];
            foreach ($backers as $b) {
                $row = ConfiguratorHwlibBacker::create([
                    'pn' => $this->pn($b['pn'], $b['description'] ?: $b['pn']),
                    'description' => $b['description'], 'notes' => $b['notes'],
                    'active' => $b['active'] ?? true, 'sort_order' => $b['sort_order'] ?? 0,
                ]);
                $backerMap[$b['id']] = $row->id;
            }
            $this->info('Imported '.count($backerMap).' backers.');

            foreach ($backerFasteners as $bf) {
                if (! isset($backerMap[$bf['backer_id']], $fastenerMap[$bf['fastener_id']])) {
                    continue;
                }
                ConfiguratorHwlibBackerFastener::create([
                    'backer_id' => $backerMap[$bf['backer_id']],
                    'fastener_id' => $fastenerMap[$bf['fastener_id']],
                    'qty' => $bf['qty'], 'notes' => $bf['notes'], 'sort_order' => $bf['sort_order'] ?? 0,
                ]);
            }
            $this->info('Imported '.count($backerFasteners).' backer-fastener links.');

            $importedItemBackers = 0;
            foreach ($itemBackers as $ib) {
                if (! isset($itemMap[$ib['item_id']])) {
                    continue;
                }
                ConfiguratorHwlibItemBacker::create([
                    'item_id' => $itemMap[$ib['item_id']],
                    'side' => $ib['side'], 'series' => $ib['series'],
                    'pn' => $ib['pn'] ? $this->pn($ib['pn'], $ib['description'] ?: $ib['pn']) : null,
                    'description' => $ib['description'], 'qty' => $ib['qty'], 'notes' => $ib['notes'],
                    'sort_order' => $ib['sort_order'] ?? 0,
                    'backer_id' => $ib['backer_id'] ? ($backerMap[$ib['backer_id']] ?? null) : null,
                ]);
                $importedItemBackers++;
            }
            $this->info("Imported {$importedItemBackers} item-backer links.");
        });

        $this->info("Created {$this->createdCount} new placeholder product(s) for hardware PNs not already in inventory.");
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
     * Ensure a Product exists for this PN, creating a placeholder if needed.
     * Returns the PN unchanged — catalog tables store PN strings, not
     * product_id (same approach as the door catalog).
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
