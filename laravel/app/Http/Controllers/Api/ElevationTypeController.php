<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdElevationType;
use App\Models\FdStageTemplate;
use App\Models\FdStageTemplateSet;
use App\Models\FdUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ElevationTypeController extends Controller
{
    public function index(Request $request)
    {
        $types = FdElevationType::orderBy('sort_order')->orderBy('name')->get();

        if ($request->boolean('with_templates')) {
            $mapTpl = fn($t) => [
                'id'              => $t->id,
                'name'            => $t->name,
                'description'     => $t->description,
                'sort_order'      => $t->sort_order,
                'blocks_next'     => (bool) $t->blocks_next,
                'default_user_id' => $t->default_user_id,
                'default_user'    => $t->defaultUser ? ['id' => $t->defaultUser->id, 'name' => $t->defaultUser->name] : null,
            ];

            $types = $types->map(function ($type) use ($mapTpl) {
                $sets = FdStageTemplateSet::with('templates.defaultUser')
                    ->where('elevation_type_id', $type->id)
                    ->orderBy('sort_order')
                    ->get();

                $setsPayload = $sets->map(fn($s) => [
                    'id'              => $s->id,
                    'name'            => $s->name,
                    'is_default'      => $s->is_default,
                    'sort_order'      => $s->sort_order,
                    'stage_templates' => $s->templates->map($mapTpl)->values(),
                ])->values();

                // Back-compat: the flat `stage_templates` key is the default tier's list.
                $defaultSet = $sets->firstWhere('is_default', true) ?? $sets->first();
                $flat = $defaultSet ? $defaultSet->templates->map($mapTpl)->values() : collect();

                return array_merge($type->toArray(), [
                    'stage_template_sets' => $setsPayload,
                    'stage_templates'     => $flat,
                ]);
            });
        }

        return response()->json(['elevation_types' => $types]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100']);

        $maxOrder = FdElevationType::max('sort_order') ?? 0;
        $type = FdElevationType::create([
            'name'       => $request->name,
            'color'      => $request->color ?? '#6b7280',
            'sort_order' => $request->sort_order ?? ($maxOrder + 1),
            'active'     => true,
        ]);

        // Every type needs at least one tier so elevations have something to seed from.
        FdStageTemplateSet::create([
            'elevation_type_id' => $type->id,
            'name'       => 'Standard',
            'sort_order' => 0,
            'is_default' => true,
        ]);

        return response()->json(['id' => $type->id, 'elevation_type' => $type], 201);
    }

    public function update(Request $request, int $id)
    {
        $type = FdElevationType::findOrFail($id);
        $type->fill($request->only(['name', 'color', 'sort_order', 'active']));
        $type->save();

        return response()->json(['elevation_type' => $type]);
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
        if ($request->has('blocks_next')) {
            $template->blocks_next = $request->boolean('blocks_next');
        }
        if ($request->filled('template_set_id')) {
            $template->template_set_id = (int) $request->template_set_id;
        }

        $template->save();

        return response()->json(['updated' => $id, 'template' => [
            'id'              => $template->id,
            'name'            => $template->name,
            'description'     => $template->description,
            'sort_order'      => $template->sort_order,
            'blocks_next'     => (bool) $template->blocks_next,
            'template_set_id' => $template->template_set_id,
        ]]);
    }

    /** Create a new stage template for an elevation type */
    public function storeTemplate(Request $request)
    {
        $request->validate([
            'elevation_type_id' => 'required|integer|exists:fd_elevation_types,id',
            'name'              => 'required|string|max:255',
            'template_set_id'   => 'sometimes|nullable|integer|exists:fd_stage_template_sets,id',
            'blocks_next'       => 'sometimes|boolean',
        ]);

        $setId = $request->template_set_id
            ?? FdStageTemplateSet::where('elevation_type_id', $request->elevation_type_id)
                ->where('is_default', true)->value('id')
            ?? FdStageTemplateSet::where('elevation_type_id', $request->elevation_type_id)
                ->orderBy('sort_order')->value('id');

        $maxOrder = FdStageTemplate::where('template_set_id', $setId)->max('sort_order') ?? 0;

        $template = FdStageTemplate::create([
            'elevation_type_id' => $request->elevation_type_id,
            'template_set_id'   => $setId,
            'name'              => $request->name,
            'description'       => $request->description ?? null,
            'sort_order'        => $maxOrder + 1,
            'blocks_next'       => $request->boolean('blocks_next', true),
            'default_user_id'   => $request->default_user_id ?? null,
        ]);

        return response()->json(['id' => $template->id, 'template' => [
            'id'              => $template->id,
            'name'            => $template->name,
            'description'     => $template->description,
            'sort_order'      => $template->sort_order,
            'blocks_next'     => (bool) $template->blocks_next,
            'template_set_id' => $template->template_set_id,
            'default_user_id' => $template->default_user_id,
            'default_user'    => null,
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
