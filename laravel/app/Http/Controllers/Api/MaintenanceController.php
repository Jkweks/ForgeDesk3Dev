<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Asset;
use App\Models\MaintenanceTask;
use App\Models\MaintenanceRecord;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function dashboard()
    {
        $machines = Machine::count();
        $assets = Asset::count();
        $activeTasks = MaintenanceTask::where('status', 'active')->count();
        $totalRecords = MaintenanceRecord::count();

        $allTasks = MaintenanceTask::where('status', 'active')->get();
        $overdueTasks = $allTasks->filter(fn($task) => $task->is_overdue)->count();
        $dueSoonTasks = $allTasks->filter(fn($task) => $task->is_due_soon)->count();

        $totalDowntime = Machine::sum('total_downtime_minutes');
        $lastService = MaintenanceRecord::latest('performed_at')->first();

        return response()->json([
            'machine_count' => $machines,
            'asset_count' => $assets,
            'active_task_count' => $activeTasks,
            'total_record_count' => $totalRecords,
            'overdue_task_count' => $overdueTasks,
            'due_soon_task_count' => $dueSoonTasks,
            'total_downtime_minutes' => $totalDowntime,
            'total_downtime_hours' => round($totalDowntime / 60, 2),
            'last_service_date' => $lastService ? $lastService->performed_at : null,
        ]);
    }

    public function upcomingTasks()
    {
        $tasks = MaintenanceTask::where('status', 'active')
            ->with('machine')
            ->get();

        $upcoming = $tasks->filter(function($task) {
            return $task->is_overdue || $task->is_due_soon;
        })->map(function($task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'machine' => $task->machine->name,
                'machine_id' => $task->machine_id,
                'priority' => $task->priority,
                'next_due_date' => $task->next_due_date,
                'is_overdue' => $task->is_overdue,
                'is_due_soon' => $task->is_due_soon,
            ];
        })->sortBy(function($task) {
            return $task['next_due_date'];
        })->values();

        return response()->json($upcoming);
    }

    public function recentRecords()
    {
        $records = MaintenanceRecord::with(['machine', 'task'])
            ->latest('performed_at')
            ->limit(10)
            ->get();

        return response()->json($records);
    }
}
