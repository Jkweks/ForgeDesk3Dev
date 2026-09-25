<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConfiguratorHwlibBacker;
use App\Models\ConfiguratorHwlibBackerFastener;
use App\Models\ConfiguratorHwlibCategory;
use App\Models\ConfiguratorHwlibFastener;
use App\Models\ConfiguratorHwlibFunction;
use App\Models\ConfiguratorHwlibItem;
use App\Models\ConfiguratorHwlibItemBacker;
use App\Models\ConfiguratorHwlibItemValue;
use App\Models\ConfiguratorHwlibLink;
use App\Models\ConfiguratorHwlibLinkValue;
use App\Models\ConfiguratorHwlibSet;
use App\Models\ConfiguratorHwlibSetItem;
use App\Models\ConfiguratorHwlibSetItemValue;
use App\Models\ConfiguratorHwlibSubcategory;
use App\Models\ConfiguratorHwlibVariable;
use App\Models\ConfiguratorSetting;
use App\Models\DoorFrameConfiguration;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ConfiguratorHwlibAdminController extends Controller
{
    /**
     * Flat admin payload — everything the Hardware Library admin screen needs
     * that isn't already covered by the nested hwlib-catalog browse endpoint:
     * the full item list (including inactive), and the backer/fastener/set
     * master lists (not scoped to any one item).
     */
    /**
     * Configurator-wide gap defaults (the gear/globe icon on the Opening
     * tab) — top/bottom/hinge/lock gap. Only bottom_gap actually drives a
     * calculation today (DoorBomGenerator's stile length); the rest are
     * stored ahead of the generators that will consume them.
     */
    public function settings()
    {
        return response()->json(['settings' => ConfiguratorSetting::current()]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'top_gap' => 'required|numeric|min:0',
            'bottom_gap' => 'required|numeric|min:0',
            'hinge_gap' => 'required|numeric|min:0',
            'lock_gap' => 'required|numeric|min:0',
        ]);

        $settings = ConfiguratorSetting::current();
        $settings->update($validated);

        return response()->json(['settings' => $settings]);
    }

    public function adminIndex()
    {
        return response()->json([
            'items' => ConfiguratorHwlibItem::with('values.variable', 'backers.backer', 'functions')->orderBy('name')->get(),
            'backers' => ConfiguratorHwlibBacker::with('fasteners.fastener')->orderBy('pn')->get(),
            'fasteners' => ConfiguratorHwlibFastener::orderBy('pn')->get(),
            'sets' => ConfiguratorHwlibSet::with('businessJob', 'setItems.item', 'setItems.values.variable', 'setItems.functions')->orderBy('name')->get(),
        ]);
    }

    // ---- Categories ----

    public function storeCategory(Request $request)
    {
        $data = $this->validateOrFail($request, $this->categoryRules());

        return response()->json(['category' => ConfiguratorHwlibCategory::create($data)], 201);
    }

    public function updateCategory(Request $request, $id)
    {
        $category = ConfiguratorHwlibCategory::findOrFail($id);
        $data = $this->validateOrFail($request, $this->categoryRules());
        $category->update($data);

        return response()->json(['category' => $category]);
    }

    public function destroyCategory($id)
    {
        ConfiguratorHwlibCategory::findOrFail($id)->delete();

        return response()->json(['message' => 'Category deleted']);
    }

    private function categoryRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ];
    }

    // ---- Subcategories ----

    public function storeSubcategory(Request $request)
    {
        $data = $this->validateOrFail($request, $this->subcategoryRules());

        return response()->json(['subcategory' => ConfiguratorHwlibSubcategory::create($data)], 201);
    }

    public function updateSubcategory(Request $request, $id)
    {
        $subcategory = ConfiguratorHwlibSubcategory::findOrFail($id);
        $data = $this->validateOrFail($request, [
            'category_id' => 'sometimes|exists:configurator_hwlib_categories,id',
            'name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer',
        ]);
        $subcategory->update($data);

        return response()->json(['subcategory' => $subcategory]);
    }

    public function destroySubcategory($id)
    {
        // Items keep their category — only the subcategory link is cleared
        // (subcategory_id ->nullOnDelete() at the DB level already does
        // this; findOrFail+delete() here goes through the same path).
        ConfiguratorHwlibSubcategory::findOrFail($id)->delete();

        return response()->json(['message' => 'Subcategory deleted']);
    }

    private function subcategoryRules(): array
    {
        return [
            'category_id' => 'required|exists:configurator_hwlib_categories,id',
            'name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer',
        ];
    }

    /**
     * Full-replace the category's assigned variables (pivot rows), preserving
     * the given order as sort_order — mirrors the reference app's
     * set_category_variables (delete-all-then-reinsert, not a diff).
     */
    public function setCategoryVariables(Request $request, $id)
    {
        $category = ConfiguratorHwlibCategory::findOrFail($id);
        $data = $this->validateOrFail($request, [
            'variable_ids' => 'present|array',
            'variable_ids.*' => 'integer|exists:configurator_hwlib_variables,id',
        ]);

        DB::transaction(function () use ($category, $data) {
            $category->categoryVariables()->delete();
            foreach (array_values($data['variable_ids']) as $i => $variableId) {
                $category->categoryVariables()->create(['variable_id' => $variableId, 'sort_order' => $i]);
            }
        });

        return response()->json(['message' => 'Category variables updated']);
    }

    // ---- Variables ----

    public function indexVariables()
    {
        return response()->json(['variables' => ConfiguratorHwlibVariable::orderBy('group_name')->orderBy('sort_order')->orderBy('label')->get()]);
    }

    public function storeVariable(Request $request)
    {
        $data = $this->validateOrFail($request, $this->variableRules());

        return response()->json(['variable' => ConfiguratorHwlibVariable::create($data)], 201);
    }

    public function updateVariable(Request $request, $id)
    {
        $variable = ConfiguratorHwlibVariable::findOrFail($id);
        $data = $this->validateOrFail($request, $this->variableRules($id));
        $variable->update($data);

        return response()->json(['variable' => $variable]);
    }

    public function destroyVariable($id)
    {
        ConfiguratorHwlibVariable::findOrFail($id)->delete();

        return response()->json(['message' => 'Variable deleted']);
    }

    private function variableRules($ignoreId = null): array
    {
        return [
            'code' => 'required|string|max:255|unique:configurator_hwlib_variables,code,'.($ignoreId ?? 'NULL'),
            'label' => 'required|string|max:255',
            'group_name' => 'required|string|max:255',
            'var_type' => 'required|in:number,text,boolean,select,degree_matrix',
            'unit' => 'nullable|string|max:50',
            'options' => 'nullable|array',
            'is_calculated' => 'boolean',
            'formula' => 'nullable|string',
            'default_value' => 'nullable|string',
            'notes' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'overrides_variable_id' => [
                'nullable', 'integer', 'exists:configurator_hwlib_variables,id',
                function ($attribute, $value, $fail) use ($ignoreId) {
                    if ($ignoreId && $value == $ignoreId) {
                        $fail('A variable cannot override itself.');
                    }
                },
            ],
            'side' => 'nullable|string|max:50',
            'show_in_report' => 'boolean',
            'is_inspection' => 'boolean',
        ];
    }

    // ---- Functions ----

    public function indexFunctions()
    {
        return response()->json(['functions' => ConfiguratorHwlibFunction::orderBy('group_name')->orderBy('sort_order')->orderBy('label')->get()]);
    }

    public function storeFunction(Request $request)
    {
        $data = $this->validateOrFail($request, $this->functionRules());

        return response()->json(['function' => ConfiguratorHwlibFunction::create($data)], 201);
    }

    public function updateFunction(Request $request, $id)
    {
        $function = ConfiguratorHwlibFunction::findOrFail($id);
        $data = $this->validateOrFail($request, $this->functionRules($id));
        $function->update($data);

        return response()->json(['function' => $function]);
    }

    public function destroyFunction($id)
    {
        ConfiguratorHwlibFunction::findOrFail($id)->delete();

        return response()->json(['message' => 'Function deleted']);
    }

    private function functionRules($ignoreId = null): array
    {
        return [
            'code' => 'required|string|max:50|unique:configurator_hwlib_functions,code,'.($ignoreId ?? 'NULL'),
            'label' => 'required|string|max:255',
            'group_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'active' => 'boolean',
        ];
    }

    // ---- Items ----

    public function storeItem(Request $request)
    {
        $data = $this->validateOrFail($request, $this->itemRules());
        $item = ConfiguratorHwlibItem::create($data);
        $item->load('category', 'values.variable', 'backers.backer', 'functions');

        return response()->json(['item' => $item], 201);
    }

    public function updateItem(Request $request, $id)
    {
        $item = ConfiguratorHwlibItem::findOrFail($id);
        $data = $this->validateOrFail($request, $this->itemRules($id));
        $item->update($data);
        $item->load('category', 'values.variable', 'backers.backer', 'functions');

        return response()->json(['item' => $item]);
    }

    public function destroyItem($id)
    {
        ConfiguratorHwlibItem::findOrFail($id)->delete();

        return response()->json(['message' => 'Item deleted']);
    }

    private function itemRules($ignoreId = null): array
    {
        return [
            'category_id' => 'required|exists:configurator_hwlib_categories,id',
            'subcategory_id' => [
                'nullable', 'integer', 'exists:configurator_hwlib_subcategories,id',
                function ($attribute, $value, $fail) {
                    if (! $value) {
                        return;
                    }
                    $categoryId = request('category_id');
                    if (! ConfiguratorHwlibSubcategory::where('id', $value)->where('category_id', $categoryId)->exists()) {
                        $fail('Subcategory does not belong to the selected category.');
                    }
                },
            ],
            'name' => 'required|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'pn' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'active' => 'boolean',
            'needs_review' => 'boolean',
            'vos_standard' => 'boolean',
            'finishes' => 'nullable|array',
            'min_width' => 'nullable|numeric',
            'max_width' => 'nullable|numeric',
            'min_height' => 'nullable|numeric',
            'max_height' => 'nullable|numeric',
            'field_install' => 'boolean',
            'handed' => 'boolean',
            'default_strike_item_id' => [
                'nullable', 'integer', 'exists:configurator_hwlib_items,id',
                function ($attribute, $value, $fail) use ($ignoreId) {
                    if ($ignoreId && $value == $ignoreId) {
                        $fail('An item cannot be its own default strike item.');
                    }
                },
            ],
            'default_cover_item_id' => [
                'nullable', 'integer', 'exists:configurator_hwlib_items,id',
                function ($attribute, $value, $fail) use ($ignoreId) {
                    if ($ignoreId && $value == $ignoreId) {
                        $fail('An item cannot be its own default cover item.');
                    }
                },
            ],
        ];
    }

    /**
     * Full-replace an item's per-variable values.
     */
    public function setItemValues(Request $request, $id)
    {
        $item = ConfiguratorHwlibItem::findOrFail($id);
        $data = $this->validateOrFail($request, [
            'values' => 'present|array',
            'values.*.variable_id' => 'required|integer|exists:configurator_hwlib_variables,id',
            'values.*.value_text' => 'nullable|string',
        ]);

        DB::transaction(function () use ($item, $data) {
            $item->values()->delete();
            foreach ($data['values'] as $value) {
                if (($value['value_text'] ?? '') === '') {
                    continue;
                }
                ConfiguratorHwlibItemValue::create([
                    'item_id' => $item->id,
                    'variable_id' => $value['variable_id'],
                    'value_text' => $value['value_text'],
                ]);
            }
        });

        return response()->json(['message' => 'Item values updated']);
    }

    /**
     * Full-replace an item's allowed functions (its picklist when linking
     * this item to a configuration).
     */
    public function setItemFunctions(Request $request, $id)
    {
        $item = ConfiguratorHwlibItem::findOrFail($id);
        $data = $this->validateOrFail($request, [
            'function_ids' => 'present|array',
            'function_ids.*' => 'integer|exists:configurator_hwlib_functions,id',
        ]);

        $item->functions()->sync($data['function_ids']);

        return response()->json(['message' => 'Item functions updated']);
    }

    /**
     * Full-replace an item's backer assignments (side/series rows).
     */
    public function setItemBackers(Request $request, $id)
    {
        $item = ConfiguratorHwlibItem::findOrFail($id);
        $data = $this->validateOrFail($request, [
            'backers' => 'present|array',
            'backers.*.side' => 'required|string|max:50',
            'backers.*.series' => 'required|string|max:50',
            'backers.*.backer_id' => 'nullable|integer|exists:configurator_hwlib_backers,id',
            'backers.*.pn' => 'nullable|string|max:255',
            'backers.*.description' => 'nullable|string|max:255',
            'backers.*.qty' => 'required|numeric',
            'backers.*.notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($item, $data) {
            $item->backers()->delete();
            foreach (array_values($data['backers']) as $i => $row) {
                ConfiguratorHwlibItemBacker::create([
                    'item_id' => $item->id,
                    'side' => $row['side'],
                    'series' => $row['series'],
                    'backer_id' => $row['backer_id'] ?? null,
                    'pn' => $row['pn'] ?? null,
                    'description' => $row['description'] ?? null,
                    'qty' => $row['qty'],
                    'notes' => $row['notes'] ?? null,
                    'sort_order' => $i,
                ]);
            }
        });

        return response()->json(['message' => 'Item backers updated']);
    }

    // ---- Backers ----

    public function storeBacker(Request $request)
    {
        $data = $this->validateOrFail($request, $this->backerRules());

        return response()->json(['backer' => ConfiguratorHwlibBacker::create($data)], 201);
    }

    public function updateBacker(Request $request, $id)
    {
        $backer = ConfiguratorHwlibBacker::findOrFail($id);
        $data = $this->validateOrFail($request, $this->backerRules($id));
        $backer->update($data);

        return response()->json(['backer' => $backer]);
    }

    public function destroyBacker($id)
    {
        try {
            ConfiguratorHwlibBacker::findOrFail($id)->delete();
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23503 || str_contains($e->getMessage(), 'foreign key')) {
                return response()->json([
                    'message' => 'This backer is still assigned to one or more items and cannot be deleted.',
                ], 409);
            }
            throw $e;
        }

        return response()->json(['message' => 'Backer deleted']);
    }

    private function backerRules($ignoreId = null): array
    {
        return [
            'pn' => 'required|string|max:255|unique:configurator_hwlib_backers,pn,'.($ignoreId ?? 'NULL'),
            'description' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ];
    }

    /**
     * Full-replace a backer's fastener list.
     */
    public function setBackerFasteners(Request $request, $id)
    {
        $backer = ConfiguratorHwlibBacker::findOrFail($id);
        $data = $this->validateOrFail($request, [
            'fasteners' => 'present|array',
            'fasteners.*.fastener_id' => 'required|integer|exists:configurator_hwlib_fasteners,id',
            'fasteners.*.qty' => 'required|numeric',
            'fasteners.*.notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($backer, $data) {
            $backer->fasteners()->delete();
            foreach (array_values($data['fasteners']) as $i => $row) {
                ConfiguratorHwlibBackerFastener::create([
                    'backer_id' => $backer->id,
                    'fastener_id' => $row['fastener_id'],
                    'qty' => $row['qty'],
                    'notes' => $row['notes'] ?? null,
                    'sort_order' => $i,
                ]);
            }
        });

        return response()->json(['message' => 'Backer fasteners updated']);
    }

    // ---- Fasteners ----

    public function storeFastener(Request $request)
    {
        $data = $this->validateOrFail($request, $this->fastenerRules());

        return response()->json(['fastener' => ConfiguratorHwlibFastener::create($data)], 201);
    }

    public function updateFastener(Request $request, $id)
    {
        $fastener = ConfiguratorHwlibFastener::findOrFail($id);
        $data = $this->validateOrFail($request, $this->fastenerRules($id));
        $fastener->update($data);

        return response()->json(['fastener' => $fastener]);
    }

    public function destroyFastener($id)
    {
        try {
            ConfiguratorHwlibFastener::findOrFail($id)->delete();
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23503 || str_contains($e->getMessage(), 'foreign key')) {
                return response()->json([
                    'message' => 'This fastener is still attached to a backer and cannot be deleted.',
                ], 409);
            }
            throw $e;
        }

        return response()->json(['message' => 'Fastener deleted']);
    }

    private function fastenerRules($ignoreId = null): array
    {
        return [
            'pn' => 'required|string|max:255|unique:configurator_hwlib_fasteners,pn,'.($ignoreId ?? 'NULL'),
            'description' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ];
    }

    // ---- Sets ----

    /**
     * A job's hardware sets, with items/values and the openings each set is
     * currently applied to — the data source for the Job Dashboard's
     * Hardware Sets tab.
     */
    public function indexSets(Request $request)
    {
        $data = $this->validateOrFail($request, [
            'business_job_id' => 'required|integer|exists:business_jobs,id',
        ]);

        $sets = ConfiguratorHwlibSet::where('business_job_id', $data['business_job_id'])
            ->with([
                'setItems.item',
                'setItems.values.variable',
                'appliedConfigurations' => fn ($q) => $q->with('doors'),
            ])
            ->orderBy('name')
            ->get();

        return response()->json(['sets' => $sets]);
    }

    public function storeSet(Request $request)
    {
        $data = $this->validateOrFail($request, $this->setRules());

        return response()->json(['set' => ConfiguratorHwlibSet::create($data)], 201);
    }

    public function updateSet(Request $request, $id)
    {
        $set = ConfiguratorHwlibSet::findOrFail($id);
        $data = $this->validateOrFail($request, $this->setRules());
        $set->update($data);

        return response()->json(['set' => $set]);
    }

    public function destroySet($id)
    {
        ConfiguratorHwlibSet::findOrFail($id)->delete();

        return response()->json(['message' => 'Set deleted']);
    }

    private function setRules(): array
    {
        return [
            'business_job_id' => 'required|integer|exists:business_jobs,id',
            'name' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'is_pair' => 'boolean',
        ];
    }

    /**
     * Full-replace a set's item rows (and each row's per-variable value
     * overrides), then cascade: re-materialize the set into every opening
     * it's currently applied to, skipping any that are already released.
     */
    public function setSetItems(Request $request, $id)
    {
        $set = ConfiguratorHwlibSet::findOrFail($id);
        $data = $this->validateOrFail($request, [
            'items' => 'present|array',
            'items.*.item_id' => 'required|integer|exists:configurator_hwlib_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.series' => 'required|string|max:50',
            'items.*.leaf' => 'required|string|max:50',
            'items.*.notes' => 'nullable|string',
            'items.*.values' => 'nullable|array',
            'items.*.values.*.variable_id' => 'required_with:items.*.values|integer|exists:configurator_hwlib_variables,id',
            'items.*.values.*.value_text' => 'nullable|string',
            'items.*.function_ids' => 'nullable|array',
            'items.*.function_ids.*' => 'integer|exists:configurator_hwlib_functions,id',
        ]);

        DB::transaction(function () use ($set, $data) {
            $set->setItems()->delete();
            foreach ($data['items'] as $row) {
                $setItem = ConfiguratorHwlibSetItem::create([
                    'set_id' => $set->id,
                    'item_id' => $row['item_id'],
                    'quantity' => $row['quantity'],
                    'series' => $row['series'],
                    'leaf' => $row['leaf'],
                    'notes' => $row['notes'] ?? null,
                ]);
                foreach ($row['values'] ?? [] as $value) {
                    if (($value['value_text'] ?? '') === '') {
                        continue;
                    }
                    ConfiguratorHwlibSetItemValue::create([
                        'set_item_id' => $setItem->id,
                        'variable_id' => $value['variable_id'],
                        'value_text' => $value['value_text'],
                    ]);
                }
                $setItem->functions()->sync($row['function_ids'] ?? []);
            }
        });

        $set->load('setItems.values', 'setItems.functions');

        $reappliedTo = [];
        $skippedReleased = [];
        foreach ($set->appliedConfigurations as $configuration) {
            if (! $configuration->canEdit()) {
                $skippedReleased[] = $configuration->id;

                continue;
            }
            DB::transaction(function () use ($set, $configuration) {
                $this->materializeSetIntoConfiguration($set, $configuration);
            });
            $reappliedTo[] = $configuration->id;
        }

        return response()->json([
            'message' => 'Set items updated',
            'reapplied_to' => $reappliedTo,
            'skipped_released' => $skippedReleased,
        ]);
    }

    /**
     * Apply a set to one or more openings (door/frame configurations) —
     * materializes the set's items as hardware links on each, tagged with
     * source_set_id so a later edit-and-resync knows which links to replace.
     */
    public function applySet(Request $request, $id)
    {
        $set = ConfiguratorHwlibSet::with('setItems.values', 'setItems.functions')->findOrFail($id);
        $data = $this->validateOrFail($request, [
            'configuration_ids' => 'required|array|min:1',
            'configuration_ids.*' => 'integer|exists:door_frame_configurations,id',
        ]);

        $configurations = DoorFrameConfiguration::whereIn('id', $data['configuration_ids'])->with('openingSpecs')->get();

        $invalidJob = $configurations->where('business_job_id', '!=', $set->business_job_id)->pluck('id');
        if ($invalidJob->isNotEmpty()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['configuration_ids' => ['Configuration(s) '.$invalidJob->implode(', ')." do not belong to this set's job."]],
            ], 422);
        }

        $notEditable = $configurations->reject(fn ($c) => $c->canEdit())->pluck('id');
        if ($notEditable->isNotEmpty()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['configuration_ids' => ['Configuration(s) '.$notEditable->implode(', ').' are not editable (already released).']],
            ], 422);
        }

        // A Pair set carries leaf-specific (active/inactive) items, which
        // only make sense on a Pair opening, and vice versa. Only enforced
        // once an opening actually has a type — a brand-new configuration
        // with no opening_type set yet has nothing to conflict with (e.g.
        // pairing a set at creation time, before the Opening tab is filled
        // in), so those are left to pass through here.
        $mismatched = $configurations->reject(function ($c) use ($set) {
            $openingType = $c->openingSpecs?->opening_type;

            return $openingType === null || ($openingType === 'pair') === $set->is_pair;
        })->pluck('id');
        if ($mismatched->isNotEmpty()) {
            $setType = $set->is_pair ? 'Pair' : 'Single';

            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['configuration_ids' => ['Configuration(s) '.$mismatched->implode(', ')." are not a {$setType} opening, which does not match this set."]],
            ], 422);
        }

        foreach ($configurations as $configuration) {
            DB::transaction(function () use ($set, $configuration) {
                $this->materializeSetIntoConfiguration($set, $configuration);
                DB::table('door_frame_configuration_hwlib_sets')->updateOrInsert(
                    ['configuration_id' => $configuration->id, 'set_id' => $set->id],
                    ['applied_at' => now(), 'updated_at' => now(), 'created_at' => now()]
                );
            });
        }

        return response()->json(['message' => 'Set applied', 'applied_to' => $configurations->pluck('id')]);
    }

    /**
     * Remove a set from one opening — deletes the pivot row and the hardware
     * links it materialized there. Manually-added links (source_set_id null)
     * are untouched.
     */
    public function unapplySet($id, $configurationId)
    {
        $set = ConfiguratorHwlibSet::findOrFail($id);
        $configuration = DoorFrameConfiguration::findOrFail($configurationId);

        if (! $configuration->canEdit()) {
            return response()->json([
                'error' => 'Cannot edit configuration',
                'message' => 'Configuration is not in editable status',
            ], 422);
        }

        DB::transaction(function () use ($set, $configuration) {
            ConfiguratorHwlibLink::where('configuration_id', $configuration->id)
                ->where('source_set_id', $set->id)
                ->delete();
            DB::table('door_frame_configuration_hwlib_sets')
                ->where('configuration_id', $configuration->id)
                ->where('set_id', $set->id)
                ->delete();
        });

        return response()->json(['message' => 'Set removed from configuration']);
    }

    /**
     * Full delete-then-reinsert of the hardware links a set previously
     * materialized on this configuration, replaced with its current items —
     * shared by applySet (first application) and setSetItems' cascade
     * (re-sync after an edit).
     */
    private function materializeSetIntoConfiguration(ConfiguratorHwlibSet $set, DoorFrameConfiguration $configuration): void
    {
        ConfiguratorHwlibLink::where('configuration_id', $configuration->id)
            ->where('source_set_id', $set->id)
            ->delete();

        foreach ($set->setItems as $setItem) {
            $link = ConfiguratorHwlibLink::updateOrCreate(
                ['configuration_id' => $configuration->id, 'item_id' => $setItem->item_id, 'leaf' => $setItem->leaf],
                ['quantity' => $setItem->quantity, 'series' => $setItem->series, 'notes' => $setItem->notes, 'source_set_id' => $set->id]
            );

            ConfiguratorHwlibLinkValue::where('link_id', $link->id)->delete();
            foreach ($setItem->values as $value) {
                if (($value->value_text ?? '') === '') {
                    continue;
                }
                ConfiguratorHwlibLinkValue::create([
                    'link_id' => $link->id,
                    'variable_id' => $value->variable_id,
                    'value_text' => $value->value_text,
                ]);
            }

            $link->functions()->sync($setItem->functions->pluck('id'));
        }
    }

    private function validateOrFail(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            abort(response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422));
        }

        return $validator->validated();
    }
}
