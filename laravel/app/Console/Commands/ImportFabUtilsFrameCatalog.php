<?php

namespace App\Console\Commands;

use App\Models\ConfiguratorFrameComponent;
use App\Models\ConfiguratorFrameProfile;
use App\Models\ConfiguratorFrameSeries;
use App\Models\ConfiguratorFrameSystem;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-time / re-runnable snapshot import of the frame catalog (systems, series,
 * extrusion profiles, components) from the standalone fab_utils configurator
 * database, dumped to JSON files under storage/app/fab_utils_import.
 *
 * Components are sourced from products.default_accessories, NOT the separate
 * frame_components/frame_fasteners tables — checking fab_utils' live frame
 * calculator (configurator/index.html) confirmed those tables are only read by
 * its admin screen and are never consulted when actually computing a BOM;
 * default_accessories is what's live. The two sources also disagree for at
 * least one PN (E2550/Threshold's P797 clip), so importing both would double
 * the quantity in the generated BOM.
 *
 * This is a full replace: existing configurator_frame_* rows are wiped and
 * reseeded from the dump each run, so it's safe to re-run after refreshing the
 * JSON exports from fab_utils.
 */
class ImportFabUtilsFrameCatalog extends Command
{
    protected $signature = 'configurator:import-fab-utils {--path=fab_utils_import : Directory under storage/app holding the exported JSON files}';

    protected $description = 'Import the fab_utils frame catalog (systems/series/profiles/components) into the configurator catalog tables';

    public function handle(): int
    {
        $dir = storage_path('app/'.$this->option('path'));

        if (! is_dir($dir)) {
            $this->error("Import directory not found: {$dir}");

            return self::FAILURE;
        }

        $systems = $this->readJson($dir.'/frame_systems.json');
        $series = $this->readJson($dir.'/frame_series.json');
        $profiles = $this->readJson($dir.'/frame_profiles.json');
        $products = $this->readJson($dir.'/products.json');
        $accessories = $this->readJson($dir.'/product_accessories.json');

        $this->info(sprintf(
            'Loaded %d systems, %d series, %d profiles, %d PNs, %d PNs with accessories.',
            count($systems), count($series), count($profiles), count($products), count($accessories)
        ));

        DB::transaction(function () use ($systems, $series, $profiles, $products, $accessories) {
            $productMap = $this->resolveProducts($products);
            $accessoriesByPn = collect($accessories)->keyBy('pn');

            // Full replace — wipe previously imported catalog rows before reseeding.
            ConfiguratorFrameSystem::query()->delete();

            $systemMap = [];
            foreach ($systems as $s) {
                $row = ConfiguratorFrameSystem::create([
                    'name' => $s['name'],
                    'code' => $s['code'],
                    'sort_order' => $s['sort_order'] ?? 0,
                ]);
                $systemMap[$s['id']] = $row->id;
            }
            $this->info('Imported '.count($systemMap).' frame systems.');

            $seriesMap = [];
            $usedCodes = [];
            foreach ($series as $s) {
                $systemId = $systemMap[$s['system_id']] ?? null;
                if (! $systemId) {
                    $this->warn("Skipping series '{$s['name']}' — unknown system_id {$s['system_id']}");

                    continue;
                }

                $code = Str::upper(Str::slug($s['name'], '_'));
                $code = substr($code, 0, 30) ?: 'SERIES';
                while (in_array($systemId.'|'.$code, $usedCodes, true)) {
                    $code = substr($code, 0, 26).'_'.random_int(10, 99);
                }
                $usedCodes[] = $systemId.'|'.$code;

                $row = ConfiguratorFrameSeries::create([
                    'frame_system_id' => $systemId,
                    'name' => $s['name'],
                    'code' => $code,
                    'sort_order' => $s['sort_order'] ?? 0,
                ]);
                $seriesMap[$s['id']] = $row->id;
            }
            $this->info('Imported '.count($seriesMap).' frame series.');

            $profileCount = 0;
            $skippedProfiles = 0;
            $componentCount = 0;
            $skippedComponents = 0;

            foreach ($profiles as $p) {
                $seriesId = $seriesMap[$p['series_id']] ?? null;
                $productId = $productMap[$p['pn']] ?? null;
                if (! $seriesId || ! $productId) {
                    $skippedProfiles++;

                    continue;
                }

                $profile = ConfiguratorFrameProfile::create([
                    'frame_series_id' => $seriesId,
                    'role_label' => $p['role'],
                    'product_id' => $productId,
                    'formula' => $p['formula'] ?? [],
                    'condition' => $p['condition'] ?? null,
                    'glass_thicknesses' => $p['glass_thicknesses'] ?? null,
                    'section_height' => $p['section_height'] ?? 0,
                    'qty_per_opening' => $p['qty_per_opening'] ?? 1,
                    'sort_order' => $p['sort_order'] ?? 0,
                ]);
                $profileCount++;

                $sortOrder = 0;
                foreach (($accessoriesByPn[$p['pn']]['default_accessories'] ?? []) as $acc) {
                    $accProductId = $productMap[$acc['pn']] ?? null;
                    if (! $accProductId) {
                        $skippedComponents++;

                        continue;
                    }

                    ConfiguratorFrameComponent::create([
                        'frame_profile_id' => $profile->id,
                        'label' => $acc['description'] ?: $acc['pn'],
                        'product_id' => $accProductId,
                        'qty_type' => $acc['qty_type'] ?? 'per_opening',
                        'qty_per' => $acc['qty'] ?? 1,
                        'sort_order' => $sortOrder++,
                    ]);
                    $componentCount++;
                }
            }

            $this->info("Imported {$profileCount} frame profiles ({$skippedProfiles} skipped — missing series/product).");
            $this->info("Imported {$componentCount} frame components from default_accessories ({$skippedComponents} skipped — missing product).");
        });

        $this->info('Import complete.');

        return self::SUCCESS;
    }

    // Frame extrusion placeholders are (virtually) all Tubelite parts —
    // matches the fixed supplier_id 1 the Door/Hwlib catalog importers use.
    private const PLACEHOLDER_SUPPLIER_ID = 1;

    private ?int $fallbackSupplierId = null;

    private function fallbackSupplierId(): ?int
    {
        if ($this->fallbackSupplierId === null) {
            $this->fallbackSupplierId = Supplier::whereKey(self::PLACEHOLDER_SUPPLIER_ID)->value('id')
                ?? Supplier::where('name', 'Tubelite')->value('id')
                ?? Supplier::query()->value('id');
        }

        return $this->fallbackSupplierId;
    }

    private function readJson(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?? [];
    }

    /**
     * Match each fab_utils PN to an existing ForgeDesk product by part_number,
     * creating a minimal placeholder product for any PN not already in inventory.
     *
     * @return array<string, int> pn => product_id
     */
    private function resolveProducts(array $products): array
    {
        $map = [];
        $created = 0;

        foreach ($products as $p) {
            $pn = $p['pn'];
            $existing = Product::withTrashed()->where('part_number', $pn)->first();

            if ($existing) {
                $map[$pn] = $existing->id;

                continue;
            }

            $new = Product::create([
                'sku' => Product::generateSku($pn),
                'part_number' => $pn,
                'description' => $p['description'] ?: $pn,
                'unit_cost' => 0,
                'quantity_on_hand' => 0,
                'quantity_committed' => 0,
                'supplier_id' => $this->fallbackSupplierId(),
                'is_special_order' => true,
                'nonsof' => true,
            ]);
            $map[$pn] = $new->id;
            $created++;
        }

        $this->info('Matched '.count($products)." PNs to products — created {$created} new placeholder product(s) for PNs not already in inventory.");

        return $map;
    }
}
