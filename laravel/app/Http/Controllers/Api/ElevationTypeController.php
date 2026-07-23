<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdElevationType;
use App\Models\FdStageTemplate;
use App\Models\FdStageTemplateDep;
use App\Models\FdUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ElevationTypeController extends Controller
{
    public function index(Request $request)
    {
        $types = FdElevationType::orderBy('sort_order')->orderBy('name')->get();

        if ($request->boolean('with_templates')) {
            $types = $types->map(function ($type) {
                $templates = FdStageTemplate::with('defaultUser')
                    ->where('elevation_type_id', $type->id)
                    ->orderBy('sort_order')
                    ->get();

                $templateIds = $templates->pluck('id');
                $depRows = FdStageTemplateDep::whereIn('template_id', $templateIds)
                    ->get()
                    ->groupBy('template_id');

                $mapped = $templates->map(fn($t) => [
                    'id'              => $t->id,
                    'name'            => $t->name,
                    'description'     => $t->description,
                    'sort_order'      => $t->sort_order,
                    'default_user_id' => $t->default_user_id,
                    'default_user'    => $t->defaultUser ? ['id' => $t->defaultUser->id, 'name' => $t->defaultUser->name] : null,
                    'depends_on'      => ($depRows->get($t->id) ?? collect())->pluck('depends_on_template_id')->values(),
                ]);
                return array_merge($type->toArray(), ['stage_templates' => $mapped]);
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

        $template->save();

        return response()->json(['updated' => $id, 'template' => [
            'id'          => $template->id,
            'name'        => $template->name,
            'description' => $template->description,
            'sort_order'  => $template->sort_order,
        ]]);
    }

    /** Create a new stage template for an elevation type */
    public function storeTemplate(Request $request)
    {
        $request->validate([
            'elevation_type_id' => 'required|integer|exists:fd_elevation_types,id',
            'name'              => 'required|string|max:255',
        ]);

        $maxOrder = FdStageTemplate::where('elevation_type_id', $request->elevation_type_id)
            ->max('sort_order') ?? 0;

        $template = FdStageTemplate::create([
            'elevation_type_id' => $request->elevation_type_id,
            'name'              => $request->name,
            'description'       => $request->description ?? null,
            'sort_order'        => $maxOrder + 1,
            'default_user_id'   => $request->default_user_id ?? null,
        ]);

        return response()->json(['id' => $template->id, 'template' => [
            'id'              => $template->id,
            'name'            => $template->name,
            'description'     => $template->description,
            'sort_order'      => $template->sort_order,
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

    /** Add a dependency between two stage templates */
    public function storeDep(Request $request, int $id)
    {
        $request->validate(['depends_on_template_id' => 'required|integer|exists:fd_stage_templates,id']);
        $template = FdStageTemplate::findOrFail($id);
        $dep = FdStageTemplateDep::firstOrCreate([
            'template_id'            => $template->id,
            'depends_on_template_id' => $request->depends_on_template_id,
        ]);
        return response()->json(['id' => $dep->id], 201);
    }

    /** Remove a dependency between two stage templates */
    public function destroyDep(int $id, int $depId)
    {
        $template = FdStageTemplate::findOrFail($id);
        FdStageTemplateDep::where('template_id', $template->id)
            ->where('depends_on_template_id', $depId)
            ->delete();
        return response()->json(['deleted' => $depId]);
    }
}
