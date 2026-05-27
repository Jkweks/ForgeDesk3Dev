<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdElevationType;
use App\Models\FdStageTemplate;
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
                    ->get()
                    ->map(fn($t) => [
                        'id'              => $t->id,
                        'name'            => $t->name,
                        'sort_order'      => $t->sort_order,
                        'default_user_id' => $t->default_user_id,
                        'default_user'    => $t->defaultUser ? ['id' => $t->defaultUser->id, 'name' => $t->defaultUser->name] : null,
                    ]);
                return array_merge($type->toArray(), ['stage_templates' => $templates]);
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

    public function updateTemplate(Request $request, int $id)
    {
        $template = FdStageTemplate::findOrFail($id);
        if ($request->has('default_user_id')) {
            $template->default_user_id = $request->default_user_id ?: null;
            $template->save();
        }
        return response()->json(['updated' => $id]);
    }
}
