<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdElevationType;
use App\Models\FdStageTemplate;
use App\Models\FdStageTemplateSet;
use Illuminate\Http\Request;

class ElevationTypeController extends Controller
{
    public function index(Request $request)
    {
        $types = FdElevationType::orderBy('sort_order')->orderBy('name')->get();

        if ($request->boolean('with_templates')) {
            $mapTpl = fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'sort_order' => $t->sort_order,
                'phase' => $t->phase,
                'blocks_next' => (bool) $t->blocks_next,
                'minutes_per_joint' => $t->minutes_per_joint !== null ? (float) $t->minutes_per_joint : null,
                'default_user_id' => $t->default_user_id,
                'default_user' => $t->defaultUser ? ['id' => $t->defaultUser->id, 'name' => $t->defaultUser->name] : null,
            ];

            $types = $types->map(function ($type) use ($mapTpl) {
                $sets = FdStageTemplateSet::with('templates.defaultUser')
                    ->where('elevation_type_id', $type->id)
                    ->orderBy('sort_order')
                    ->get();

                $setsPayload = $sets->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'is_default' => $s->is_default,
                    'sort_order' => $s->sort_order,
                    'minutes_per_joint' => $s->minutes_per_joint !== null ? (float) $s->minutes_per_joint : null,
                    'stage_templates' => $s->templates->map($mapTpl)->values(),
                ])->values();

                // Back-compat: the flat `stage_templates` key is the default tier's list.
                $defaultSet = $sets->firstWhere('is_default', true) ?? $sets->first();
                $flat = $defaultSet ? $defaultSet->templates->map($mapTpl)->values() : collect();

                return array_merge($type->toArray(), [
                    'stage_template_sets' => $setsPayload,
                    'stage_templates' => $flat,
                ]);
            });
        }

        return response()->json(['elevation_types' => $types]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'aliases' => 'sometimes|array',
            'aliases.*' => 'nullable|string|max:100',
            'standard_joint_count' => 'sometimes|nullable|integer|min:0|max:65535',
        ]);

        $maxOrder = FdElevationType::max('sort_order') ?? 0;
        $type = FdElevationType::create([
            'name' => $request->name,
            'aliases' => $this->cleanAliases($request->input('aliases'), $request->name),
            'color' => $request->color ?? '#6b7280',
            'sort_order' => $request->sort_order ?? ($maxOrder + 1),
            'standard_joint_count' => $request->filled('standard_joint_count') ? (int) $request->standard_joint_count : null,
            'active' => true,
        ]);

        // Every type needs at least one tier so elevations have something to seed from.
        FdStageTemplateSet::create([
            'elevation_type_id' => $type->id,
            'name' => 'Standard',
            'sort_order' => 0,
            'is_default' => true,
        ]);

        return response()->json(['id' => $type->id, 'elevation_type' => $type], 201);
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'name' => 'sometimes|string|max:100',
            'aliases' => 'sometimes|array',
            'aliases.*' => 'nullable|string|max:100',
            'standard_joint_count' => 'sometimes|nullable|integer|min:0|max:65535',
        ]);

        $type = FdElevationType::findOrFail($id);
        $type->fill($request->only(['name', 'color', 'sort_order', 'active']));

        if ($request->has('aliases')) {
            $type->aliases = $this->cleanAliases($request->input('aliases'), $type->name);
        }

        if ($request->has('standard_joint_count')) {
            $type->standard_joint_count = $request->filled('standard_joint_count') ? (int) $request->standard_joint_count : null;
        }

        $type->save();

        return response()->json(['elevation_type' => $type]);
    }

    /**
     * Normalise an alias list from the form: trim, drop blanks, drop any that
     * just repeat the type name, and de-dupe case-insensitively (first spelling
     * wins). Returns a plain re-indexed array for the JSON column.
     *
     * @return list<string>
     */
    private function cleanAliases($raw, ?string $name): array
    {
        $nameKey = mb_strtolower(trim((string) $name));

        return collect(is_array($raw) ? $raw : [])
            ->map(fn ($s) => trim((string) $s))
            ->filter(fn ($s) => $s !== '' && mb_strtolower($s) !== $nameKey)
            ->unique(fn ($s) => mb_strtolower($s))
            ->values()
            ->all();
    }

    public function destroy(int $id)
    {
        $type = FdElevationType::findOrFail($id);
        $type->active = false;
        $type->save();

        return response()->json(['deactivated' => $id]);
    }

    /** Update a stage template (name, description, sort_order, default_user_id) */
    public function updateTemplate(Request $request, int $id)
    {
        $template = FdStageTemplate::findOrFail($id);

        if ($request->has('default_user_id')) {
            $template->default_user_id = $request->default_user_id ?: null;
        }
        if ($request->has('name')) {
            $template->name = $request->name;
        }
        if ($request->has('description')) {
            $template->description = $request->description ?: null;
        }
        if ($request->has('sort_order')) {
            $template->sort_order = (int) $request->sort_order;
        }
        if ($request->has('phase')) {
            $template->phase = $request->filled('phase') ? max(1, (int) $request->phase) : null;
        }
        if ($request->has('blocks_next')) {
            $template->blocks_next = $request->boolean('blocks_next');
        }
        if ($request->has('minutes_per_joint')) {
            $template->minutes_per_joint = $request->filled('minutes_per_joint')
                ? max(0, round((float) $request->minutes_per_joint, 2))
                : null;
        }
        if ($request->filled('template_set_id')) {
            $template->template_set_id = (int) $request->template_set_id;
        }

        $template->save();

        return response()->json(['updated' => $id, 'template' => [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'sort_order' => $template->sort_order,
            'phase' => $template->phase,
            'blocks_next' => (bool) $template->blocks_next,
            'minutes_per_joint' => $template->minutes_per_joint !== null ? (float) $template->minutes_per_joint : null,
            'template_set_id' => $template->template_set_id,
        ]]);
    }

    /** Create a new stage template for an elevation type */
    public function storeTemplate(Request $request)
    {
        $request->validate([
            'elevation_type_id' => 'required|integer|exists:fd_elevation_types,id',
            'name' => 'required|string|max:255',
            'template_set_id' => 'sometimes|nullable|integer|exists:fd_stage_template_sets,id',
            'phase' => 'sometimes|nullable|integer|min:1',
            'blocks_next' => 'sometimes|boolean',
            'minutes_per_joint' => 'sometimes|nullable|numeric|min:0',
        ]);

        $setId = $request->template_set_id
            ?? FdStageTemplateSet::where('elevation_type_id', $request->elevation_type_id)
                ->where('is_default', true)->value('id')
            ?? FdStageTemplateSet::where('elevation_type_id', $request->elevation_type_id)
                ->orderBy('sort_order')->value('id');

        $maxOrder = FdStageTemplate::where('template_set_id', $setId)->max('sort_order') ?? 0;

        $template = FdStageTemplate::create([
            'elevation_type_id' => $request->elevation_type_id,
            'template_set_id' => $setId,
            'name' => $request->name,
            'description' => $request->description ?? null,
            'sort_order' => $maxOrder + 1,
            'phase' => $request->filled('phase') ? max(1, (int) $request->phase) : null,
            'blocks_next' => $request->boolean('blocks_next', true),
            'minutes_per_joint' => $request->filled('minutes_per_joint') ? max(0, round((float) $request->minutes_per_joint, 2)) : null,
            'default_user_id' => $request->default_user_id ?? null,
        ]);

        return response()->json(['id' => $template->id, 'template' => [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'sort_order' => $template->sort_order,
            'phase' => $template->phase,
            'blocks_next' => (bool) $template->blocks_next,
            'minutes_per_joint' => $template->minutes_per_joint !== null ? (float) $template->minutes_per_joint : null,
            'template_set_id' => $template->template_set_id,
            'default_user_id' => $template->default_user_id,
            'default_user' => null,
        ]], 201);
    }

    /** Delete a stage template */
    public function destroyTemplate(int $id)
    {
        $template = FdStageTemplate::findOrFail($id);
        $template->delete();

        return response()->json(['deleted' => $id]);
    }
}
