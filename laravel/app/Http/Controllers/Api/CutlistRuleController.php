<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CutlistRule;
use App\Models\CutlistRuleSetting;
use App\Services\CutFlow\CutlistIngestService;
use App\Services\CutFlow\CutlistRuleEngine;
use App\Services\CutFlow\SillQuarterPointService;
use App\Services\CutFlow\SillQuarterPointSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Admin-only (route middleware `admin`): view/adapt/create cut-list integrity rules and the sill quarter-point settings. */
class CutlistRuleController extends Controller
{
    /** Structural checks the sill report always makes (the layout maths can't run without them). */
    private const BUILT_IN = [
        ['Every SILL row has a bay number, and a bay has only one SILL row.', 'sill'],
        ['Every bay has exactly one FEMALE VERTICAL row (except the last bay).', 'sill'],
        ['An elevation has exactly one LEFT JAMB and one RIGHT JAMB row (door jambs excluded).', 'sill'],
        ['Bay numbers run 1..N with a sill or a door threshold in each.', 'sill'],
        ['A door bay may not be the far-left bay.', 'sill'],
        ['Knee walls are detected where neighbouring vertical heights differ by more than the knee-wall tolerance.', 'sill'],
        ['Receptor channel pieces are matched to sill runs; a run is flagged when layout and receptor differ by more than the flag tolerance.', 'sill'],
        ['Finish codes must be known and each part/finish must match an inventory product (warned at import).', 'import'],
    ];

    public function index()
    {
        $settings = SillQuarterPointSettings::effective();

        return response()->json([
            'rules' => CutlistRule::orderBy('sort_order')->orderBy('id')->get(),
            'meta' => [
                'fields' => CutlistRuleEngine::FIELDS, 'ops' => CutlistRuleEngine::OPS,
                'metrics' => CutlistRuleEngine::METRICS, 'compare_ops' => CutlistRuleEngine::COMPARE_OPS,
            ],
            'built_in' => array_map(fn ($b) => ['text' => $b[0], 'area' => $b[1]], self::BUILT_IN),
            'settings' => [
                'effective' => $settings,
                'defaults' => SillQuarterPointSettings::defaults(),
                'overridden' => array_keys(SillQuarterPointSettings::overrides()),
            ],
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'kind' => ['required', Rule::in(CutlistRule::KINDS)],
            'severity' => ['required', Rule::in(CutlistRule::SEVERITIES)],
            'enabled' => 'boolean',
            'definition' => 'required|array',
            'message' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $errors = CutlistRuleEngine::validateDefinition($data['kind'], $data['definition']);
        if ($errors) {
            throw ValidationException::withMessages(['definition' => $errors]);
        }

        return $data;
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()?->id;
        $data['sort_order'] ??= (int) CutlistRule::max('sort_order') + 10;

        return response()->json(CutlistRule::create($data), 201);
    }

    public function update(Request $request, CutlistRule $rule)
    {
        $rule->update($this->validated($request));

        return response()->json($rule->fresh());
    }

    public function destroy(CutlistRule $rule)
    {
        $rule->delete();

        return response()->json(['ok' => true]);
    }

    /** Saves sill quarter-point settings. Values equal to the config default are stored as "no override". */
    public function updateSettings(Request $request)
    {
        $rules = [
            'part_widths' => 'nullable|array', 'part_widths.*' => 'numeric|min:0|max:48',
            'roles' => 'nullable|array', 'roles.*' => 'string|max:100',
            'door_tags' => 'nullable|array', 'door_tags.*' => ['string', 'max:100', 'regex:/^\{[^{}]+\}$/'],
        ];
        foreach (SillQuarterPointSettings::SCALARS as $key => [$type, $min, $max]) {
            $rules[$key] = ['nullable', $type === 'int' ? 'integer' : 'numeric', "min:$min", "max:$max"];
        }
        $data = $request->validate($rules);
        if (isset($data['fraction_denominator']) && ! in_array((int) $data['fraction_denominator'], [2, 4, 8, 16, 32, 64, 128], true)) {
            throw ValidationException::withMessages(['fraction_denominator' => 'Must be 2, 4, 8, 16, 32, 64 or 128.']);
        }
        foreach (array_keys($data['roles'] ?? []) as $k) {
            if (! isset(SillQuarterPointSettings::ROLE_DEFAULTS[$k])) {
                throw ValidationException::withMessages(['roles' => "Unknown role {$k}."]);
            }
        }

        $defaults = SillQuarterPointSettings::defaults();
        foreach ($data as $key => $value) {
            if ($value === null || $value === [] || (is_array($value) && $value == ($defaults[$key] ?? null))
                || (! is_array($value) && (is_numeric($value) && is_numeric($defaults[$key] ?? null) && (float) $value === (float) $defaults[$key]))) {
                CutlistRuleSetting::where('key', $key)->delete();

                continue;
            }
            if ($key === 'part_widths') {
                $value = array_map('floatval', $value);
            } elseif ($key === 'roles') {
                $value = array_map(fn ($v) => strtoupper(trim($v)), $value);
            } elseif ($key === 'door_tags') {
                $value = array_values(array_map(fn ($v) => strtoupper($v), $value));
            } elseif (is_numeric($value)) {
                $value = SillQuarterPointSettings::SCALARS[$key][0] === 'int' ? (int) $value : (float) $value;
            }
            CutlistRuleSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return $this->index();
    }

    /** Back to config defaults. */
    public function resetSettings()
    {
        CutlistRuleSetting::query()->delete();

        return $this->index();
    }

    /** Dry run: runs the enabled rules and the sill report against an uploaded CSV without importing it. */
    public function test(Request $request, CutlistIngestService $ingest, CutlistRuleEngine $engine, SillQuarterPointService $sill)
    {
        $request->validate(['csv' => 'required|file|mimes:csv,txt']);
        $file = $request->file('csv');

        $rows = $engine->rowsFromRaw($ingest->parseCsv($file));
        $report = $sill->build($file->getRealPath());

        return response()->json([
            'rows' => count($rows),
            'findings' => $engine->evaluate($rows),
            'sill' => [
                'elevations' => count($report['elevations']),
                'needs_review' => $report['needs_review'],
                'receptor_flags' => $report['receptor_flags'],
                'assumptions' => $report['assumptions'],
            ],
        ]);
    }
}
