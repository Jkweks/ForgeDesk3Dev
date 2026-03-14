<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $query = Asset::with('machines');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|max:255',
            'description' => 'nullable',
            'documents' => 'nullable|array',
            'notes' => 'nullable',
            'machine_ids' => 'nullable|array',
            'machine_ids.*' => 'exists:machines,id',
        ]);

        $asset = Asset::create($validated);

        if (isset($validated['machine_ids'])) {
            $asset->machines()->sync($validated['machine_ids']);
        }

        return response()->json($asset->load('machines'), 201);
    }

    public function show(Asset $asset)
    {
        return response()->json($asset->load([
            'machines',
            'maintenanceRecords'
        ]));
    }

    public function update(Request $request, Asset $asset)
    {
        $validated = $request->validate([
            'name' => 'required|max:255',
            'description' => 'nullable',
            'documents' => 'nullable|array',
            'notes' => 'nullable',
            'machine_ids' => 'nullable|array',
            'machine_ids.*' => 'exists:machines,id',
        ]);

        $asset->update($validated);

        if (isset($validated['machine_ids'])) {
            $asset->machines()->sync($validated['machine_ids']);
        }

        return response()->json($asset->load('machines'));
    }

    public function destroy(Asset $asset)
    {
        $asset->delete();
        return response()->json(null, 204);
    }
}
