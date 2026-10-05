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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Imports the hardware library (hwlib_*) from the standalone fab_utils configurator database (JSON dumps
 * under storage/app/fab_utils_import). Does NOT import hwlib_saved_config_links — those are fab_utils'
 * own historical per-job hardware selections, not reusable catalog data.
 *
 * Safe to re-run at any time: it UPSERTS by natural key instead of wiping and recreating, so ids stay put
 * and nothing that points at the catalog (sets, hardware links on configurations, link values) is ever
 * disturbed. Natural keys: variable code, category name, item name, backer pn, fastener pn.
 *
 * It only ever writes the fields fab_utils supplies. ForgeDesk-only data — item subcategories, item
 * functions, needs_review, hardware sets, and anything a ForgeDesk admin added — is left alone, and a
 * `handed` flag is only ever switched on, never off.
 *
 * Modes:
 *   (default)       sync: fab_utils is authoritative for the fields it supplies. Existing rows are updated
 *                   to match and each matched item's values / backers (and each matched category's variable
 *                   list) mirror the dump.
 *   --create-only   add what is missing and change nothing that exists — for after ForgeDesk, not
 *                   fab_utils, is where the catalog is maintained.
 *   --prune         also delete rows an earlier import brought in that the dump no longer has, but only when
 *                   nothing references them. Rows created in ForgeDesk are never pruned.
 *   --dry-run       report what would change and write nothing.
 */
class ImportFabUtilsHwlibCatalog extends Command
{
    protected $signature = 'configurator:import-fab-utils-hwlib
        {--path=fab_utils_import : Directory under storage/app holding the exported JSON files}
        {--create-only : Only create rows that do not exist; never change or remove existing ones}
        {--prune : Also delete catalog rows missing from the dump, if nothing references them}
        {--dry-run : Show what would change; write nothing}';

    protected $description = 'Import (upsert) the fab_utils hardware library into the configurator catalog tables without disturbing sets, links or ForgeDesk-only data';

    private const PLACEHOLDER_SUPPLIER_ID = 1;

    private array $productCache = [];

    private int $createdCount = 0;

    /** @var array<string, array{created: int, updated: int, unchanged: int, removed: int}> */
    private array $stats = [];

    private bool $createOnly = false;

    /** @var array<string, array<int, int>> entity => ids created by this run */
    private array $created = [];

    public function handle(): int
    {
        $dir = storage_path('app/'.$this->option('path'));

        if (! is_dir($dir)) {
            $this->error("Import directory not found: {$dir}");

            return self::FAILURE;
        }

        if ($this->option('create-only') && $this->option('prune')) {
            $this->error('--create-only and --prune contradict each other.');

            return self::FAILURE;
        }

        $this->createOnly = (bool) $this->option('create-only');
        $this->stats = [];
        $this->created = [];
        $this->createdCount = 0;
        $this->productCache = [];

        $data = [
            'variables' => $this->readJson($dir.'/hwlib_variables.json'),
            'categories' => $this->readJson($dir.'/hwlib_categories.json'),
            'categoryVariables' => $this->readJson($dir.'/hwlib_category_variables.json'),
            'items' => $this->readJson($dir.'/hwlib_items.json'),
            'itemValues' => $this->readJson($dir.'/hwlib_item_values.json'),
            'fasteners' => $this->readJson($dir.'/hwlib_fasteners.json'),
            'backers' => $this->readJson($dir.'/hwlib_backers.json'),
            'backerFasteners' => $this->readJson($dir.'/hwlib_backer_fasteners.json'),
            'itemBackers' => $this->readJson($dir.'/hwlib_item_backers.json'),
        ];

        $this->info(sprintf(
            'Loaded %d variables, %d categories, %d category-variable links, %d items, %d item values, %d fasteners, %d backers, %d backer-fasteners, %d item-backers.',
            ...array_map('count', array_values($data))
        ));
        $this->info(($this->option('dry-run') ? 'DRY RUN — ' : '').($this->createOnly ? 'create-only' : 'sync').($this->option('prune') ? ' + prune' : '').' mode.');

        DB::beginTransaction();
        try {
            $this->import($data);
            $this->option('dry-run') ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->report();
        $this->info("Created {$this->createdCount} new placeholder product(s) for hardware PNs not already in inventory.");
        $this->info($this->option('dry-run') ? 'Dry run complete — nothing was written.' : 'Import complete.');

        return self::SUCCESS;
    }

    private function import(array $data): void
    {
        $variableMap = $this->importVariables($data['variables']);
        $categoryMap = $this->importCategories($data['categories']);
        $this->importCategoryVariables($data['categoryVariables'], $categoryMap, $variableMap);
        $itemMap = $this->importItems($data['items'], $categoryMap);
        $this->applyForgeDeskAdditions();
        $this->importItemValues($data['itemValues'], $itemMap, $variableMap);
        $fastenerMap = $this->importSimple('fasteners', ConfiguratorHwlibFastener::class, $data['fasteners']);
        $backerMap = $this->importSimple('backers', ConfiguratorHwlibBacker::class, $data['backers']);
        $this->importBackerFasteners($data['backerFasteners'], $backerMap, $fastenerMap);
        $this->importItemBackers($data['itemBackers'], $itemMap, $backerMap);

        if ($this->option('prune') && ! $this->createOnly) {
            $this->prune($data, $itemMap);
        }
    }

    // ── upsert plumbing ─────────────────────────────────────────────────────────────────

    private function bump(string $entity, string $what, int $by = 1): void
    {
        $this->stats[$entity] ??= ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'removed' => 0];
        $this->stats[$entity][$what] += $by;
    }

    /**
     * Find by natural key; create if missing; otherwise (unless --create-only) update to the dump's values.
     * Only the given $values are ever written.
     *
     * @param  class-string<Model>  $class
     */
    private function put(string $entity, string $class, array $key, array $values): Model
    {
        $row = $class::where($key)->first();

        if (! $row) {
            $row = $class::create($key + $values);
            $this->created[$entity][] = $row->id;
            $this->bump($entity, 'created');
        } elseif ($this->createOnly) {
            $this->bump($entity, 'unchanged');
        } else {
            $row->fill($values);
            if ($row->isDirty()) {
                $row->save();
                $this->bump($entity, 'updated');
            } else {
                $this->bump($entity, 'unchanged');
            }
        }

        // Metadata only: remember this row came from fab_utils (see prune()).
        if (in_array($entity, ['categories', 'items', 'backers', 'fasteners'], true)) {
            $class::whereKey($row->id)->whereNull('imported_at')->update(['imported_at' => now()]);
        }

        return $row;
    }

    private function wasCreated(string $entity, int $id): bool
    {
        return in_array($id, $this->created[$entity] ?? [], true);
    }

    // ── entities ────────────────────────────────────────────────────────────────────────

    /** @return array<int, int> fab_utils variable id => ForgeDesk id */
    private function importVariables(array $variables): array
    {
        $map = [];
        foreach ($variables as $v) {
            $row = $this->put('variables', ConfiguratorHwlibVariable::class, ['code' => $v['code']], [
                'label' => $v['label'], 'group_name' => $v['group_name'],
                'var_type' => $v['var_type'], 'unit' => $v['unit'], 'options' => $v['options'] ?? [],
                'is_calculated' => $v['is_calculated'], 'formula' => $v['formula'],
                'default_value' => $v['default_value'], 'notes' => $v['notes'], 'sort_order' => $v['sort_order'] ?? 0,
                'side' => $v['side'], 'show_in_report' => $v['show_in_report'] ?? true, 'is_inspection' => $v['is_inspection'] ?? false,
            ]);
            $map[$v['id']] = $row->id;
        }

        // overrides_variable_id self-references need every variable to exist first.
        foreach ($variables as $v) {
            $target = $v['overrides_variable_id'] ?? null;
            if (! $target || ! isset($map[$target])) {
                continue;
            }
            $id = $map[$v['id']];
            if (! $this->createOnly || $this->wasCreated('variables', $id)) {
                ConfiguratorHwlibVariable::whereKey($id)->update(['overrides_variable_id' => $map[$target]]);
            }
        }

        return $map;
    }

    /** @return array<int, int> */
    private function importCategories(array $categories): array
    {
        $map = [];
        foreach ($categories as $c) {
            $map[$c['id']] = $this->put('categories', ConfiguratorHwlibCategory::class, ['name' => $c['name']], [
                'description' => $c['description'], 'sort_order' => $c['sort_order'] ?? 0,
            ])->id;
        }

        return $map;
    }

    private function importCategoryVariables(array $rows, array $categoryMap, array $variableMap): void
    {
        $wanted = []; // category id => [variable ids]
        foreach ($rows as $cv) {
            if (! isset($categoryMap[$cv['category_id']], $variableMap[$cv['variable_id']])) {
                continue;
            }
            $categoryId = $categoryMap[$cv['category_id']];
            $variableId = $variableMap[$cv['variable_id']];
            $wanted[$categoryId][] = $variableId;

            $this->put('category variables', ConfiguratorHwlibCategoryVariable::class,
                ['category_id' => $categoryId, 'variable_id' => $variableId], ['sort_order' => $cv['sort_order'] ?? 0]);
        }

        if (! $this->createOnly) {
            foreach ($wanted as $categoryId => $variableIds) {
                $stale = ConfiguratorHwlibCategoryVariable::where('category_id', $categoryId)->whereNotIn('variable_id', $variableIds);
                $this->bump('category variables', 'removed', $stale->count());
                $stale->delete();
            }
        }
    }

    /** @return array<int, int> */
    private function importItems(array $items, array $categoryMap): array
    {
        $map = [];
        $skipped = 0;
        foreach ($items as $i) {
            if (! isset($categoryMap[$i['category_id']])) {
                $skipped++;

                continue;
            }

            $values = [
                'category_id' => $categoryMap[$i['category_id']],
                'manufacturer' => $i['manufacturer'], 'model_number' => $i['model_number'],
                'pn' => $this->pn($i['pn'] ?: null, $i['name']), 'notes' => $i['notes'], 'active' => $i['active'] ?? true,
                'vos_standard' => $i['vos_standard'] ?? false, 'finishes' => $i['finishes'] ?? [],
                'min_width' => $i['min_width'], 'max_width' => $i['max_width'],
                'min_height' => $i['min_height'], 'max_height' => $i['max_height'],
                'field_install' => $i['field_install'] ?? false,
            ];

            // `handed` is only ever switched on: fab_utils leaves most handed parts unflagged, ForgeDesk flags them.
            $existing = ConfiguratorHwlibItem::where('name', $i['name'])->first();
            if (! $existing || ! $existing->handed || ($i['handed'] ?? false)) {
                $values['handed'] = $i['handed'] ?? false;
            }

            $map[$i['id']] = $this->put('items', ConfiguratorHwlibItem::class, ['name' => $i['name']], $values)->id;
        }

        // default strike / cover self-references. Never cleared: ForgeDesk wires some itself (see additions).
        foreach ($items as $i) {
            if (! isset($map[$i['id']])) {
                continue;
            }
            $id = $map[$i['id']];
            if ($this->createOnly && ! $this->wasCreated('items', $id)) {
                continue;
            }
            $update = [];
            foreach (['default_strike_item_id', 'default_cover_item_id'] as $field) {
                if (($i[$field] ?? null) && isset($map[$i[$field]])) {
                    $update[$field] = $map[$i[$field]];
                }
            }
            if ($update) {
                ConfiguratorHwlibItem::whereKey($id)->update($update);
            }
        }

        if ($skipped) {
            $this->warn("{$skipped} item(s) skipped — category missing from the dump.");
        }

        return $map;
    }

    private function importItemValues(array $rows, array $itemMap, array $variableMap): void
    {
        $wanted = []; // item id => [variable ids]
        foreach ($rows as $iv) {
            if (! isset($itemMap[$iv['item_id']], $variableMap[$iv['variable_id']])) {
                continue;
            }
            $itemId = $itemMap[$iv['item_id']];
            $variableId = $variableMap[$iv['variable_id']];
            if ($this->createOnly && ! $this->wasCreated('items', $itemId)) {
                continue;
            }
            $wanted[$itemId][] = $variableId;
            $this->put('item values', ConfiguratorHwlibItemValue::class, ['item_id' => $itemId, 'variable_id' => $variableId], ['value_text' => $iv['value_text']]);
        }

        if (! $this->createOnly) {
            // Mirror the dump for every matched item (items with no values in the dump end up with none).
            foreach ($itemMap as $itemId) {
                $stale = ConfiguratorHwlibItemValue::where('item_id', $itemId)->whereNotIn('variable_id', $wanted[$itemId] ?? []);
                $this->bump('item values', 'removed', $stale->count());
                $stale->delete();
            }
        }
    }

    /**
     * Fasteners and backers: keyed by part number.
     *
     * @param  class-string<Model>  $class
     * @return array<int, int>
     */
    private function importSimple(string $entity, string $class, array $rows): array
    {
        $map = [];
        foreach ($rows as $r) {
            $map[$r['id']] = $this->put($entity, $class, ['pn' => $this->pn($r['pn'], $r['description'] ?: $r['pn'])], [
                'description' => $r['description'], 'notes' => $r['notes'],
                'active' => $r['active'] ?? true, 'sort_order' => $r['sort_order'] ?? 0,
            ])->id;
        }

        return $map;
    }

    private function importBackerFasteners(array $rows, array $backerMap, array $fastenerMap): void
    {
        $wanted = []; // backer id => [fastener ids]
        foreach ($rows as $bf) {
            if (! isset($backerMap[$bf['backer_id']], $fastenerMap[$bf['fastener_id']])) {
                continue;
            }
            $backerId = $backerMap[$bf['backer_id']];
            $fastenerId = $fastenerMap[$bf['fastener_id']];
            $wanted[$backerId][] = $fastenerId;
            $this->put('backer fasteners', ConfiguratorHwlibBackerFastener::class, ['backer_id' => $backerId, 'fastener_id' => $fastenerId],
                ['qty' => $bf['qty'], 'notes' => $bf['notes'], 'sort_order' => $bf['sort_order'] ?? 0]);
        }

        if (! $this->createOnly) {
            foreach ($backerMap as $backerId) {
                $stale = ConfiguratorHwlibBackerFastener::where('backer_id', $backerId)->whereNotIn('fastener_id', $wanted[$backerId] ?? []);
                $this->bump('backer fasteners', 'removed', $stale->count());
                $stale->delete();
            }
        }
    }

    /**
     * An item's backers have no natural key of their own, so each matched item's set is compared as a whole:
     * identical -> untouched; different -> replaced with the dump's. (Nothing references these rows.)
     */
    private function importItemBackers(array $rows, array $itemMap, array $backerMap): void
    {
        $byItem = [];
        foreach ($rows as $ib) {
            if (isset($itemMap[$ib['item_id']])) {
                $byItem[$itemMap[$ib['item_id']]][] = [
                    'side' => $ib['side'], 'series' => $ib['series'],
                    'pn' => $ib['pn'] ? $this->pn($ib['pn'], $ib['description'] ?: $ib['pn']) : null,
                    'description' => $ib['description'], 'qty' => $ib['qty'], 'notes' => $ib['notes'],
                    'sort_order' => $ib['sort_order'] ?? 0,
                    'backer_id' => $ib['backer_id'] ? ($backerMap[$ib['backer_id']] ?? null) : null,
                ];
            }
        }

        $signature = fn (array $list) => collect($list)->map(fn ($r) => implode('|', [
            $r['side'], $r['series'], $r['pn'], $r['description'], (float) $r['qty'], $r['notes'], (int) $r['sort_order'], $r['backer_id'],
        ]))->sort()->values()->all();

        foreach ($itemMap as $itemId) {
            $source = $byItem[$itemId] ?? [];
            if ($this->createOnly && ! $this->wasCreated('items', $itemId)) {
                continue;
            }

            $existing = ConfiguratorHwlibItemBacker::where('item_id', $itemId)->get()->map(fn ($r) => [
                'side' => $r->side, 'series' => $r->series, 'pn' => $r->pn, 'description' => $r->description,
                'qty' => $r->qty, 'notes' => $r->notes, 'sort_order' => $r->sort_order, 'backer_id' => $r->backer_id,
            ])->all();

            if ($signature($existing) === $signature($source)) {
                $this->bump('item backers', 'unchanged');

                continue;
            }

            ConfiguratorHwlibItemBacker::where('item_id', $itemId)->delete();
            foreach ($source as $row) {
                ConfiguratorHwlibItemBacker::create($row + ['item_id' => $itemId]);
            }
            $this->bump('item backers', $existing ? 'updated' : 'created');
        }
    }

    /**
     * Remove catalog rows fab_utils has since dropped — but only rows that an earlier import brought in
     * (imported_at is set; ForgeDesk-created rows never are), only ones nothing references, and never a
     * ForgeDesk addition. Anything still in use is reported, not touched.
     */
    private function prune(array $data, array $itemMap): void
    {
        $keepItems = array_merge(array_column($data['items'], 'name'), array_column(self::FORGEDESK_ITEMS, 'name'));
        foreach (ConfiguratorHwlibItem::whereNotNull('imported_at')->whereNotIn('name', $keepItems)->get() as $item) {
            $inUse = DB::table('configurator_hwlib_links')->where('item_id', $item->id)->exists()
                || DB::table('configurator_hwlib_set_items')->where('item_id', $item->id)->exists();
            if ($inUse) {
                $this->warn("Not pruned (in use): item '{$item->name}'");

                continue;
            }
            $item->delete();
            $this->bump('items', 'removed');
        }

        foreach (ConfiguratorHwlibBacker::whereNotNull('imported_at')->whereNotIn('pn', array_filter(array_column($data['backers'], 'pn')))->get() as $backer) {
            if (ConfiguratorHwlibItemBacker::where('backer_id', $backer->id)->exists()) {
                $this->warn("Not pruned (in use): backer {$backer->pn}");

                continue;
            }
            $backer->delete();
            $this->bump('backers', 'removed');
        }

        foreach (ConfiguratorHwlibFastener::whereNotNull('imported_at')->whereNotIn('pn', array_filter(array_column($data['fasteners'], 'pn')))->get() as $fastener) {
            if (ConfiguratorHwlibBackerFastener::where('fastener_id', $fastener->id)->exists()) {
                $this->warn("Not pruned (in use): fastener {$fastener->pn}");

                continue;
            }
            $fastener->delete();
            $this->bump('fasteners', 'removed');
        }

        foreach (ConfiguratorHwlibCategory::whereNotNull('imported_at')->whereNotIn('name', array_column($data['categories'], 'name'))->get() as $category) {
            if (ConfiguratorHwlibItem::where('category_id', $category->id)->exists()) {
                $this->warn("Not pruned (still has items): category '{$category->name}'");

                continue;
            }
            $category->delete();
            $this->bump('categories', 'removed');
        }
    }

    private function report(): void
    {
        $this->table(['', 'created', 'updated', 'unchanged', 'removed'], collect($this->stats)->map(
            fn ($s, $entity) => [$entity, $s['created'], $s['updated'], $s['unchanged'], $s['removed']]
        )->values()->all());
    }

    /**
     * Part numbers (hwlib item `pn`) whose stock is handed — fab_utils' catalog doesn't flag them,
     * but ForgeDesk stocks them per hand (P1421L-0R / P1421R-0R), so the BOM must add the L/R suffix.
     */
    private const HANDED_PNS = ['P1421'];

    /**
     * Items ForgeDesk needs that don't exist in fab_utils' catalog. Re-applied after every full
     * replace so a reseed never loses them. Flagged needs_review so someone confirms the data.
     * `cover_for` wires the item as the default cover of the item with that PN (if it has none).
     */
    private const FORGEDESK_ITEMS = [
        [
            'name' => 'Adams Rite - 4510 Cover (handed)', 'category' => 'Other', 'manufacturer' => 'Adams Rite',
            'model_number' => '4510 Cover', 'pn' => 'P1411', 'handed' => true, 'vos_standard' => true,
            'finishes' => ['C2', 'BL', 'DB'], 'cover_for' => 'P1421',
            'notes' => 'ForgeDesk addition: cover for the 4510 deadlatch (stocked as P1411L/R). Verify PN.',
        ],
    ];

    private function applyForgeDeskAdditions(): void
    {
        $flagged = ConfiguratorHwlibItem::whereIn('pn', self::HANDED_PNS)->update(['handed' => true]);

        $added = 0;
        foreach (self::FORGEDESK_ITEMS as $def) {
            $category = ConfiguratorHwlibCategory::where('name', $def['category'])->first();
            if (! $category) {
                $this->warn("ForgeDesk addition '{$def['name']}' skipped — no '{$def['category']}' category.");

                continue;
            }

            $item = ConfiguratorHwlibItem::firstOrCreate(['name' => $def['name']], [
                'category_id' => $category->id, 'manufacturer' => $def['manufacturer'], 'model_number' => $def['model_number'],
                'pn' => $def['pn'], 'handed' => $def['handed'], 'vos_standard' => $def['vos_standard'],
                'finishes' => $def['finishes'], 'notes' => $def['notes'], 'active' => true, 'needs_review' => true,
            ]);
            $added++;

            if (! empty($def['cover_for'])) {
                ConfiguratorHwlibItem::where('pn', $def['cover_for'])->whereNull('default_cover_item_id')
                    ->update(['default_cover_item_id' => $item->id]);
            }
        }

        $this->info("Applied ForgeDesk additions: {$flagged} item(s) flagged handed, {$added} extra item(s).");
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
