<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceTask;
use Illuminate\Http\Request;

class MaintenanceTaskController extends Controller
{
    public function index(Request $request)
    {
        $query = MaintenanceTask::with(['machine', 'maintenanceRecords']);

        if ($request->has('machine_id')) {
            $query->where('machine_id', $request->machine_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('overdue')) {
            $tasks = $query->get()->filter(function($task) {
                return $task->is_overdue;
            })->values();
            return response()->json($tasks);
        }

        if ($request->has('due_soon')) {
            $tasks = $query->get()->filter(function($task) {
                return $task->is_due_soon;
            })->values();
            return response()->json($tasks);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'title' => 'required|max:255',
            'description' => 'nullable',
            'frequency' => 'nullable|max:255',
            'assigned_to' => 'nullable|max:255',
            'interval_count' => 'nullable|integer|min:1',
            'interval_unit' => 'nullable|in:day,week,month,year',
            'start_date' => 'nullable|date',
            'status' => 'nullable|in:active,paused,retired',
            'priority' => 'nullable|in:low,medium,high,critical',
        ]);

        $task = MaintenanceTask::create($validated);

        return response()->json($task->load('machine'), 201);
    }

    public function show(MaintenanceTask $task)
    {
        return response()->json($task->load([
            'machine',
            'maintenanceRecords'
        ]));
    }

    public function update(Request $request, MaintenanceTask $task)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'title' => 'required|max:255',
            'description' => 'nullable',
            'frequency' => 'nullable|max:255',
            'assigned_to' => 'nullable|max:255',
            'interval_count' => 'nullable|integer|min:1',
            'interval_unit' => 'nullable|in:day,week,month,year',
            'start_date' => 'nullable|date',
            'status' => 'nullable|in:active,paused,retired',
            'priority' => 'nullable|in:low,medium,high,critical',
        ]);

        $task->update($validated);

        return response()->json($task->load('machine'));
    }

    public function destroy(MaintenanceTask $task)
    {
        $task->delete();
        return response()->json(null, 204);
    }
}
