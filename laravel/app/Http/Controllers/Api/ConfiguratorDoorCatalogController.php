<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConfiguratorDoorType;
use App\Models\ConfiguratorGlassSpec;
use App\Models\ConfiguratorHingeSpacingStandard;
use App\Models\ConfiguratorMidLug;
use App\Models\ConfiguratorRail;
use App\Models\ConfiguratorRailLug;
use App\Models\ConfiguratorSettingBlockKit;
use App\Models\ConfiguratorTieRod;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ConfiguratorDoorCatalogController extends Controller
{
    /**
     * All door catalog reference tables in one payload — they're flat lookup
     * tables (not a hierarchy like the frame catalog), so the door builder and
     * admin screen both just want everything at once.
     */
    public function index()
    {
        return response()->json([
            'door_types' => ConfiguratorDoorType::orderBy('series')->orderBy('stile_name')->get(),
            'rails' => ConfiguratorRail::orderBy('rail_type')->orderBy('value_in')->get(),
            'rail_lugs' => ConfiguratorRailLug::orderBy('rail_pn')->get(),
            'mid_lugs' => ConfiguratorMidLug::orderBy('rail_pn')->get(),
            'glass_specs' => ConfiguratorGlassSpec::orderBy('thickness')->get(),
            'setting_block_kits' => ConfiguratorSettingBlockKit::orderBy('series')->orderBy('glass_thickness')->get(),
            'tie_rods' => ConfiguratorTieRod::orderBy('series')->orderBy('min_len')->get(),
            'hinge_spacing_standards' => ConfiguratorHingeSpacingStandard::orderBy('name')->get(),
        ]);
    }

    /**
     * Autocomplete/validation source for the door catalog's PN fields —
     * matches against Product.part_number only (never sku), since a PN typed
     * here (e.g. "E4544") identifies a part, not a specific finish/SKU row.
     */
    public function searchProductsByPartNumber(Request $request)
    {
        $term = trim((string) $request->get('q', ''));

        if (strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $like = '%'.strtolower($term).'%';

        $products = Product::whereNotNull('part_number')
            ->whereRaw('LOWER(part_number) LIKE ?', [$like])
            ->orderBy('part_number')
            ->limit(20)
            ->get(['id', 'part_number', 'description', 'finish']);

        return response()->json(['data' => $products]);
    }

    /**
     * Reverse lookup: every door catalog row that references a given part
     * number, so the inventory side can show "used in configurator" for a
     * Product without the catalog tables holding a product_id FK.
     */
    public function partNumberUsage(Request $request)
    {
        $partNumber = trim((string) $request->query('part_number', ''));

        if ($partNumber === '') {
            return response()->json(['usages' => []]);
        }

        $manifest = [
            [
                'entity' => 'Door Type',
                'model' => ConfiguratorDoorType::class,
                'fields' => ['bev_pn', 'rab_pn', 'cp_pn', 'ast_pn', 'inact_pn'],
                'label' => fn ($r) => trim("{$r->series} {$r->stile_name}"),
            ],
            [
                'entity' => 'Rail',
                'model' => ConfiguratorRail::class,
                'fields' => ['std_pn', 'thermal_pn', 'mon_pn', 'stacked_std_pn', 'stacked_thermal_pn', 'stacked_mon_pn'],
                'label' => fn ($r) => trim("{$r->rail_type} {$r->label}"),
            ],
            [
                'entity' => 'Rail Lug',
                'model' => ConfiguratorRailLug::class,
                'fields' => ['rail_pn', 'lug_pn'],
                'label' => fn ($r) => $r->rail_pn,
            ],
            [
                'entity' => 'Mid Lug',
                'model' => ConfiguratorMidLug::class,
                'fields' => ['rail_pn', 'lug_pn', 'f1_pn', 'f2_pn'],
                'label' => fn ($r) => $r->rail_pn,
            ],
            [
                'entity' => 'Glass Spec',
                'model' => ConfiguratorGlassSpec::class,
                'fields' => ['stop_pn', 'gasket_pn', 'gasket2_pn'],
                'label' => fn ($r) => "{$r->thickness}\" glass",
            ],
            [
                'entity' => 'Setting Block Kit',
                'model' => ConfiguratorSettingBlockKit::class,
                'fields' => ['kit1_pn', 'kit2_pn'],
                'label' => fn ($r) => trim("{$r->series} {$r->glass_thickness}"),
            ],
            [
                'entity' => 'Tie Rod',
                'model' => ConfiguratorTieRod::class,
                'fields' => ['pn'],
                'label' => fn ($r) => $r->series ? "{$r->series} tie rod" : 'Tie rod',
            ],
        ];

        $usages = [];

        foreach ($manifest as $entry) {
            $rows = $entry['model']::where(function ($q) use ($entry, $partNumber) {
                foreach ($entry['fields'] as $field) {
                    $q->orWhere($field, $partNumber);
                }
            })->get();

            foreach ($rows as $row) {
                foreach ($entry['fields'] as $field) {
                    if ($row->{$field} === $partNumber) {
                        $usages[] = [
                            'entity' => $entry['entity'],
                            'id' => $row->id,
                            'label' => ($entry['label'])($row),
                            'field' => $field,
                        ];
                    }
                }
            }
        }

        return response()->json(['usages' => $usages]);
    }

    // ---- Door Types ----

    public function storeDoorType(Request $request)
    {
        $data = $this->validateOrFail($request, $this->doorTypeRules());
        $this->assertUniqueDoorType($data['series'], $data['stile_name']);

        return response()->json(['door_type' => ConfiguratorDoorType::create($data)], 201);
    }

    public function updateDoorType(Request $request, $id)
    {
        $row = ConfiguratorDoorType::findOrFail($id);
        $data = $this->validateOrFail($request, $this->doorTypeRules());
        $this->assertUniqueDoorType($data['series'], $data['stile_name'], $id);
        $row->update($data);

        return response()->json(['door_type' => $row]);
    }

    public function destroyDoorType($id)
    {
        ConfiguratorDoorType::findOrFail($id)->delete();

        return response()->json(['message' => 'Door type deleted']);
    }

    private function doorTypeRules(): array
    {
        return [
            'series' => 'required|in:STANDARD,THERMAL,MONUMENTAL',
            'stile_name' => 'required|string|max:50',
            'stile_height' => 'required|numeric|min:0',
            'bev_pn' => 'nullable|string|max:20',
            'rab_pn' => 'nullable|string|max:20',
            'cp_pn' => 'nullable|string|max:20',
            'ast_pn' => 'nullable|string|max:20',
            'inact_pn' => 'nullable|string|max:20',
        ];
    }

    private function assertUniqueDoorType(string $series, string $stileName, $ignoreId = null): void
    {
        $exists = ConfiguratorDoorType::where('series', $series)
            ->where('stile_name', $stileName)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            abort(response()->json([
                'message' => 'Validation failed',
                'errors' => ['stile_name' => ['This series/stile combination already exists.']],
            ], 422));
        }
    }

    // ---- Rails ----

    public function storeRail(Request $request)
    {
        $data = $this->validateOrFail($request, $this->railRules());
        $this->assertUniqueRail($data['rail_type'], $data['label']);

        return response()->json(['rail' => ConfiguratorRail::create($data)], 201);
    }

    public function updateRail(Request $request, $id)
    {
        $row = ConfiguratorRail::findOrFail($id);
        $data = $this->validateOrFail($request, $this->railRules());
        $this->assertUniqueRail($data['rail_type'], $data['label'], $id);
        $row->update($data);

        return response()->json(['rail' => $row]);
    }

    private function assertUniqueRail(string $railType, string $label, $ignoreId = null): void
    {
        $exists = ConfiguratorRail::where('rail_type', $railType)
            ->where('label', $label)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            abort(response()->json([
                'message' => 'Validation failed',
                'errors' => ['label' => ['This rail type/label combination already exists.']],
            ], 422));
        }
    }

    public function destroyRail($id)
    {
        ConfiguratorRail::findOrFail($id)->delete();

        return response()->json(['message' => 'Rail deleted']);
    }

    private function railRules(): array
    {
        return [
            'rail_type' => 'required|in:top,bot,mid',
            'label' => 'required|string|max:40',
            'std_pn' => 'nullable|string|max:20',
            'thermal_pn' => 'nullable|string|max:20',
            'mon_pn' => 'nullable|string|max:20',
            'value_in' => 'required|numeric|min:0',
            'stacked_std_pn' => 'nullable|string|max:20',
            'stacked_thermal_pn' => 'nullable|string|max:20',
            'stacked_mon_pn' => 'nullable|string|max:20',
        ];
    }

    // ---- Rail Lugs ----

    public function storeRailLug(Request $request)
    {
        $data = $this->validateOrFail($request, [
            'rail_pn' => 'required|string|max:20|unique:configurator_rail_lugs,rail_pn',
            'lug_pn' => 'required|string|max:30',
        ]);

        return response()->json(['rail_lug' => ConfiguratorRailLug::create($data)], 201);
    }

    public function updateRailLug(Request $request, $id)
    {
        $row = ConfiguratorRailLug::findOrFail($id);
        $data = $this->validateOrFail($request, [
            'rail_pn' => 'required|string|max:20|unique:configurator_rail_lugs,rail_pn,'.$id,
            'lug_pn' => 'required|string|max:30',
        ]);
        $row->update($data);

        return response()->json(['rail_lug' => $row]);
    }

    public function destroyRailLug($id)
    {
        ConfiguratorRailLug::findOrFail($id)->delete();

        return response()->json(['message' => 'Rail lug deleted']);
    }

    // ---- Mid Lugs ----

    public function storeMidLug(Request $request)
    {
        $data = $this->validateOrFail($request, $this->midLugRules('configurator_mid_lugs,rail_pn'));

        return response()->json(['mid_lug' => ConfiguratorMidLug::create($data)], 201);
    }

    public function updateMidLug(Request $request, $id)
    {
        $row = ConfiguratorMidLug::findOrFail($id);
        $data = $this->validateOrFail($request, $this->midLugRules('configurator_mid_lugs,rail_pn,'.$id));
        $row->update($data);

        return response()->json(['mid_lug' => $row]);
    }

    public function destroyMidLug($id)
    {
        ConfiguratorMidLug::findOrFail($id)->delete();

        return response()->json(['message' => 'Mid lug deleted']);
    }

    private function midLugRules(string $uniqueRule): array
    {
        return [
            'rail_pn' => "required|string|max:20|unique:{$uniqueRule}",
            'lug_pn' => 'required|string|max:30',
            'f1_pn' => 'nullable|string|max:20',
            'f1_qty' => 'nullable|numeric|min:0',
            'f2_pn' => 'nullable|string|max:20',
            'f2_qty' => 'nullable|numeric|min:0',
        ];
    }

    // ---- Glass Specs ----

    public function storeGlassSpec(Request $request)
    {
        $data = $this->validateOrFail($request, $this->glassSpecRules('configurator_glass_specs,thickness'));

        return response()->json(['glass_spec' => ConfiguratorGlassSpec::create($data)], 201);
    }

    public function updateGlassSpec(Request $request, $id)
    {
        $row = ConfiguratorGlassSpec::findOrFail($id);
        $data = $this->validateOrFail($request, $this->glassSpecRules('configurator_glass_specs,thickness,'.$id));
        $row->update($data);

        return response()->json(['glass_spec' => $row]);
    }

    public function destroyGlassSpec($id)
    {
        ConfiguratorGlassSpec::findOrFail($id)->delete();

        return response()->json(['message' => 'Glass spec deleted']);
    }

    private function glassSpecRules(string $uniqueRule): array
    {
        return [
            'thickness' => "required|string|max:10|unique:{$uniqueRule}",
            'stop_pn' => 'nullable|string|max:20',
            'gasket_pn' => 'nullable|string|max:20',
            'gasket2_pn' => 'nullable|string|max:20',
            'gasket_qty_factor' => 'nullable|numeric|min:0',
            'stop_height' => 'nullable|numeric|min:0',
        ];
    }

    // ---- Setting Block Kits ----

    public function storeSettingBlockKit(Request $request)
    {
        $data = $this->validateOrFail($request, $this->sbkRules());

        return response()->json(['setting_block_kit' => ConfiguratorSettingBlockKit::create($data)], 201);
    }

    public function updateSettingBlockKit(Request $request, $id)
    {
        $row = ConfiguratorSettingBlockKit::findOrFail($id);
        $data = $this->validateOrFail($request, $this->sbkRules());
        $row->update($data);

        return response()->json(['setting_block_kit' => $row]);
    }

    public function destroySettingBlockKit($id)
    {
        ConfiguratorSettingBlockKit::findOrFail($id)->delete();

        return response()->json(['message' => 'Setting block kit deleted']);
    }

    private function sbkRules(): array
    {
        return [
            'series' => 'required|in:STANDARD,THERMAL,MONUMENTAL',
            'glass_thickness' => 'required|string|max:10',
            'kit1_pn' => 'nullable|string|max:20',
            'kit2_pn' => 'nullable|string|max:20',
        ];
    }

    // ---- Tie Rods ----

    public function storeTieRod(Request $request)
    {
        $data = $this->validateOrFail($request, $this->tieRodRules());

        return response()->json(['tie_rod' => ConfiguratorTieRod::create($data)], 201);
    }

    public function updateTieRod(Request $request, $id)
    {
        $row = ConfiguratorTieRod::findOrFail($id);
        $data = $this->validateOrFail($request, $this->tieRodRules());
        $row->update($data);

        return response()->json(['tie_rod' => $row]);
    }

    public function destroyTieRod($id)
    {
        ConfiguratorTieRod::findOrFail($id)->delete();

        return response()->json(['message' => 'Tie rod deleted']);
    }

    private function tieRodRules(): array
    {
        return [
            'pn' => 'required|string|max:20',
            'min_len' => 'nullable|numeric|min:0',
            'max_len' => 'nullable|numeric|min:0',
            'series' => 'nullable|string|max:50',
            'mid_val' => 'nullable|numeric',
        ];
    }

    // ---- Hinge Spacing Standards ----

    public function storeHingeSpacingStandard(Request $request)
    {
        $data = $this->validateOrFail($request, $this->hingeSpacingStandardRules());

        return response()->json(['hinge_spacing_standard' => ConfiguratorHingeSpacingStandard::create($data)], 201);
    }

    public function updateHingeSpacingStandard(Request $request, $id)
    {
        $row = ConfiguratorHingeSpacingStandard::findOrFail($id);
        $data = $this->validateOrFail($request, $this->hingeSpacingStandardRules());
        $row->update($data);

        return response()->json(['hinge_spacing_standard' => $row]);
    }

    public function destroyHingeSpacingStandard($id)
    {
        ConfiguratorHingeSpacingStandard::findOrFail($id)->delete();

        return response()->json(['message' => 'Hinge spacing standard deleted']);
    }

    private function hingeSpacingStandardRules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'top_distance' => 'required|numeric|min:0',
            'top_label' => 'required|string|max:50',
            'bottom_distance' => 'required|numeric|min:0',
            'bottom_reference' => 'required|in:door_bottom,floor',
            'bottom_label' => 'required|string|max:50',
            'notes' => 'nullable|string',
        ];
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
