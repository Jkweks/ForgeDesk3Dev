<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdElevationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ElevationTypeController extends Controller
{
    public function index()
    {
        $types = FdElevationType::orderBy('sort_order')->orderBy('name')->get();
        return response()->json(['elevation_types' => $types]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100']);

        $maxOrder = FdElevationType::max('sort_order') ?? 0;
        $type = FdElevationType::create([
            'name'       => $request->name,
            'color'      => $request->color ?? '#6b7280',
            'sort_order' => $maxOrder + 1,
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
}
