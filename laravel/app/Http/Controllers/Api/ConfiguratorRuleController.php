<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConfiguratorFrameProfile;
use App\Models\ConfiguratorFrameSeries;
use App\Models\ConfiguratorFrameSystem;
use App\Models\ConfiguratorRule;
use App\Models\DoorFrameConfiguration;
use App\Models\Product;
use App\Services\Configurator\ConfiguratorRuleEngine;
use App\Services\Configurator\DoorBomGenerator;
use App\Services\Configurator\FrameBomGenerator;
use App\Services\Configurator\HwlibBomGenerator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Configurator Admin > Rules: CRUD for ConfiguratorRule plus a dry run against a real opening.
 * The rules themselves are evaluated by ConfiguratorRuleEngine.
 */
class ConfiguratorRuleController extends Controller
{
    public function index()
    {
        return response()->json([
            'rules' => ConfiguratorRule::orderBy('sort_order')->orderBy('id')->get(),
            'meta' => [
                'scopes' => ConfiguratorRuleEngine::SCOPES,
                'actions' => ConfiguratorRuleEngine::ACTIONS,
                'fields' => ConfiguratorRuleEngine::FIELDS,
                'built_ins' => ConfiguratorRuleEngine::BUILT_INS,
                'field_options' => [
                    'opening_type' => ['single', 'pair'],
                    'has_threshold' => ['yes', 'no'],
                    'has_transom' => ['yes', 'no'],
                    'job_scope' => array_keys(DoorFrameConfiguration::$jobScopes),
                    'frame_system' => ConfiguratorFrameSystem::orderBy('name')->pluck('name')->unique()->values(),
                    'frame_series' => ConfiguratorFrameSeries::orderBy('code')->pluck('code')->unique()->values(),
                ],
                // Frame part labels a rule can match on.
                'frame_roles' => ConfiguratorFrameProfile::distinct()->orderBy('role_label')->pluck('role_label'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort_order'] ??= (int) ConfiguratorRule::max('sort_order') + 10;

        return response()->json(['rule' => ConfiguratorRule::create($data)], 201);
    }

    public function update(Request $request, ConfiguratorRule $rule)
    {
        $rule->update($this->validated($request));

        return response()->json(['rule' => $rule->fresh()]);
    }

    public function destroy(ConfiguratorRule $rule)
    {
        $rule->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Dry run of the saved, enabled rules against one opening: which fire, their messages, and the
     * BOM rows they add or change. Nothing is saved.
     */
    public function test(Request $request, FrameBomGenerator $frameGen, DoorBomGenerator $doorGen, HwlibBomGenerator $hwGen, ConfiguratorRuleEngine $engine)
    {
        $data = $request->validate(['configuration_id' => 'required|integer|exists:door_frame_configurations,id']);
        $config = DoorFrameConfiguration::with(['openingSpecs', 'frameConfig', 'doorConfigs', 'hardwareLinks.item'])->findOrFail($data['configuration_id']);

        $status = $engine->status($config);
        $names = ConfiguratorRule::pluck('name', 'id');
        $scopes = [];
        $generators = [
            'frame' => $config->includesFrame() ? $frameGen : null,
            'door' => $config->includesDoor() ? $doorGen : null,
            'hardware' => $hwGen,
        ];
        foreach ($generators as $scope => $generator) {
            if (! $generator) {
                continue;
            }
            try {
                $result = $generator->generate($config);
            } catch (RuntimeException $e) {
                $scopes[$scope] = ['skipped' => $e->getMessage(), 'rows' => [], 'warnings' => []];

                continue;
            }
            $ruleRows = array_values(array_filter($result['rows'], fn ($r) => ! empty($r['rule_id'])));
            $products = Product::whereIn('id', array_filter(array_column($ruleRows, 'product_id')))->pluck('part_number', 'id');
            $scopes[$scope] = [
                'skipped' => null,
                'rows' => array_map(fn ($r) => [
                    'part_label' => $r['part_label'],
                    'part_number' => $products[$r['product_id'] ?? 0] ?? null,
                    'length' => $r['calculated_length'] ?? null,
                    'quantity' => $r['quantity'] ?? null,
                    'rule' => $names[$r['rule_id']] ?? null,
                ], $ruleRows),
                'warnings' => array_values(array_filter($result['warnings'], fn ($w) => str_starts_with($w, 'Rule "'))),
            ];
        }

        return response()->json(['fired' => $status['fired'], 'messages' => $status['messages'], 'scopes' => $scopes]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'enabled' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'conditions' => 'nullable|array',
            'actions' => 'required|array|min:1',
        ]);
        $errors = ConfiguratorRuleEngine::validate($data['conditions'] ?? null, $data['actions']);
        if ($errors) {
            throw ValidationException::withMessages(['actions' => $errors]);
        }

        return $data;
    }
}
