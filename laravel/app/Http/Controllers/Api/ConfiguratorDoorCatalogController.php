<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConfiguratorDoorType;
use App\Models\ConfiguratorGlassSpec;
use App\Models\ConfiguratorMidLug;
use App\Models\ConfiguratorRail;
use App\Models\ConfiguratorRailLug;
use App\Models\ConfiguratorSettingBlockKit;
use App\Models\ConfiguratorTieRod;
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
        ]);
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
