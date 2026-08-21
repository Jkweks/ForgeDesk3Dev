<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRecord;
use Illuminate\Http\Request;

class MaintenanceRecordController extends Controller
{
    public function index(Request $request)
    {
        $query = MaintenanceRecord::with(['machine', 'task', 'asset', 'performer']);

        if ($request->has('machine_id')) {
            $query->where('machine_id', $request->machine_id);
        }

        if ($request->has('task_id')) {
            $query->where('task_id', $request->task_id);
        }

        if ($request->has('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }

        if ($request->has('performed_by')) {
            $query->where('performed_by', $request->performed_by);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('performed_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('performed_at', '<=', $request->date_to);
        }

        $query->latest('performed_at');

        $perPage = $request->get('per_page', 25);

        if ($perPage === 'all') {
            return response()->json($query->get());
        }

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'task_id' => 'nullable|exists:maintenance_tasks,id',
            'asset_id' => 'nullable|exists:assets,id',
            'performed_by' => 'nullable|exists:users,id',
            'performed_at' => 'nullable|date',
            'notes' => 'nullable',
            'downtime_minutes' => 'nullable|integer|min:0',
            'labor_hours' => 'nullable|numeric|min:0',
            'parts_used' => 'nullable|array',
            'attachments' => 'nullable|array',
        ]);

        $record = MaintenanceRecord::create($validated);

        return response()->json($record->load(['machine', 'task', 'asset', 'performer']), 201);
    }

    public function show(MaintenanceRecord $record)
    {
        return response()->json($record->load([
            'machine',
            'task',
            'asset',
            'performer',
        ]));
    }

    public function update(Request $request, MaintenanceRecord $record)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'task_id' => 'nullable|exists:maintenance_tasks,id',
            'asset_id' => 'nullable|exists:assets,id',
            'performed_by' => 'nullable|exists:users,id',
            'performed_at' => 'nullable|date',
            'notes' => 'nullable',
            'downtime_minutes' => 'nullable|integer|min:0',
            'labor_hours' => 'nullable|numeric|min:0',
            'parts_used' => 'nullable|array',
            'attachments' => 'nullable|array',
        ]);

        $record->update($validated);

        return response()->json($record->load(['machine', 'task', 'asset', 'performer']));
    }

    public function destroy(MaintenanceRecord $record)
    {
        $record->delete();

        return response()->json(null, 204);
    }
}
