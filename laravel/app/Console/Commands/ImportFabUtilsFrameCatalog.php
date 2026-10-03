<?php

namespace App\Console\Commands;

use App\Models\ConfiguratorFrameComponent;
use App\Models\ConfiguratorFrameFastener;
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
        // Explicit per-profile components/fasteners (fab_utils frame_components / frame_fasteners) —
        // in addition to the product-level default_accessories above; its calculator applies both.
        $frameComponents = collect($this->readJson($dir.'/frame_components.json'))->groupBy('profile_id');
        $frameFasteners = collect($this->readJson($dir.'/frame_fasteners.json'))->groupBy('component_id');

        $this->info(sprintf(
            'Loaded %d systems, %d series, %d profiles, %d PNs, %d PNs with accessories.',
            count($systems), count($series), count($profiles), count($products), count($accessories)
        ));

        DB::transaction(function () use ($systems, $series, $profiles, $products, $accessories, $frameComponents, $frameFasteners) {
            $productMap = $this->resolveProducts($products);

            // Stock substitutions: fab_utils' generic part number -> the part ForgeDesk actually stocks.
            foreach (self::STOCK_SUBSTITUTIONS as $from => $to) {
                $target = Product::where('part_number', $to)->first();
                if ($target) {
                    $productMap[$from] = $target->id;
                    $this->info("Substituted {$from} -> {$target->sku} in the frame catalog.");
                } else {
                    $this->warn("Substitution {$from} -> {$to} skipped — no product with part number {$to}.");
                }
            }
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
            $fastenerCount = 0;

            foreach ($profiles as $p) {
                $seriesId = $seriesMap[$p['series_id']] ?? null;
                $productId = $productMap[$p['pn']] ?? null;
                if (! $seriesId || ! $productId) {
                    $skippedProfiles++;

                    continue;
                }

                // Stick length for the stock-length page / job reservation (fab_utils keeps it per
                // profile, 288" for every frame extrusion). Stored on every finish of the part, never
                // overwriting one that already has a length.
                if (! empty($p['stock_length'])) {
                    Product::where('part_number', $p['pn'])->whereNull('configurator_length')->update(['configurator_length' => $p['stock_length']]);
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
                        // fab_utils' calculator treats an accessory with no qty_type as
                        // per_length (e.g. felt weatherstripping, reported in linear feet),
                        // not per_opening — defaulting wrong here undercounted it ~5x.
                        'qty_type' => $acc['qty_type'] ?? 'per_length',
                        'qty_per' => $acc['qty'] ?? 1,
                        'glass_thicknesses' => ! empty($acc['glass_thicknesses']) ? $acc['glass_thicknesses'] : null,
                        'sort_order' => $sortOrder++,
                    ]);
                    $componentCount++;
                }

                foreach ($frameComponents[$p['id']] ?? [] as $fc) {
                    $fcProductId = $productMap[$fc['pn']] ?? null;
                    if (! $fcProductId) {
                        $skippedComponents++;

                        continue;
                    }

                    $component = ConfiguratorFrameComponent::create([
                        'frame_profile_id' => $profile->id,
                        'label' => $fc['description'] ?: $fc['pn'],
                        'product_id' => $fcProductId,
                        'qty_type' => $fc['qty_type'] ?? 'per_opening',
                        'qty_per' => $fc['qty_value'] ?? 1,
                        'sort_order' => $sortOrder++,
                    ]);
                    $componentCount++;

                    foreach ($frameFasteners[$fc['id']] ?? [] as $i => $ff) {
                        $ffProductId = $productMap[$ff['pn']] ?? null;
                        if (! $ffProductId) {
                            continue;
                        }

                        ConfiguratorFrameFastener::create([
                            'frame_component_id' => $component->id,
                            'label' => $ff['description'] ?: $ff['pn'],
                            'product_id' => $ffProductId,
                            'qty_per' => $ff['qty_per_component'] ?? 1,
                            'sort_order' => $i,
                        ]);
                        $fastenerCount++;
                    }
                }
            }

            $this->info("Imported {$profileCount} frame profiles ({$skippedProfiles} skipped — missing series/product).");
            $this->info("Imported {$componentCount} frame components (default_accessories + frame_components; {$skippedComponents} skipped — missing product), {$fastenerCount} fasteners.");
        });

        $this->info('Import complete.');

        return self::SUCCESS;
    }

    /**
     * fab_utils part number => part number to use instead. The storefront gasket is generic "P2728" in
     * fab_utils, but ForgeDesk only holds it as sized rolls; the 250' roll (P2728-250) is the one in use
     * (the 500' is being phased out), so frame gaskets reserve against it.
     */
    public const STOCK_SUBSTITUTIONS = ['P2728' => 'P2728-250'];

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
