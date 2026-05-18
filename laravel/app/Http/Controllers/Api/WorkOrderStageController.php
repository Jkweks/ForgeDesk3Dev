<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdWoStage;
use App\Models\FdStageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WorkOrderStageController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['wo_id' => 'required|integer']);

        try {
            $stages = FdWoStage::with(['assignedTo', 'log'])
                ->where('work_order_id', $request->wo_id)
                ->orderBy('sort_order')
                ->get()
                ->map(fn($s) => $this->formatStage($s));

            return response()->json(['stages' => $stages]);
        } catch (\Exception $e) {
            Log::error('WorkOrderStageController@index failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to load stages'], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'work_order_id' => 'required|integer|exists:fd_work_orders,id',
            'name'          => 'required|string|max:255',
        ]);

        try {
            $maxOrder = FdWoStage::where('work_order_id', $request->work_order_id)->max('sort_order') ?? 0;

            $stage = FdWoStage::create([
                'work_order_id' => $request->work_order_id,
                'name'          => $request->name,
                'description'   => $request->description,
                'sort_order'    => $maxOrder + 1,
                'status'        => 'pending',
                'assigned_to_id' => $request->assigned_to_id,
                'notes'         => $request->notes,
            ]);

            return response()->json(['id' => $stage->id], 201);
        } catch (\Exception $e) {
            Log::error('WorkOrderStageController@store failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create stage'], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $stage = FdWoStage::findOrFail($id);

            $allowed = ['name', 'description', 'status', 'sort_order', 'assigned_to_id', 'notes', 'started_at', 'completed_at'];
            $stage->fill($request->only($allowed));

            // Auto-timestamp on status transitions
            if ($request->has('status')) {
                if ($request->status === 'in_progress' && is_null($stage->started_at)) {
                    $stage->started_at = now();
                }
                if ($request->status === 'complete' && is_null($stage->completed_at)) {
                    $stage->completed_at = now();
                }
                if ($request->status === 'pending') {
                    $stage->started_at   = null;
                    $stage->completed_at = null;
                }
            }

            $stage->save();

            // Append log entry if provided
            if ($request->filled('log_message')) {
                FdStageLog::create([
                    'stage_id' => $stage->id,
                    'user_id'  => $request->user_id ?? null,
                    'message'  => $request->log_message,
                ]);
            }

            return response()->json(['updated' => $id]);
        } catch (\Exception $e) {
            Log::error('WorkOrderStageController@update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to update stage'], 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            $stage = FdWoStage::findOrFail($id);
            $stage->delete();

            return response()->json(['deleted' => $id]);
        } catch (\Exception $e) {
            Log::error('WorkOrderStageController@destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to delete stage'], 500);
        }
    }

    private function formatStage(FdWoStage $s): array
    {
        return [
            'id'             => $s->id,
            'work_order_id'  => $s->work_order_id,
            'template_id'    => $s->template_id,
            'name'           => $s->name,
            'description'    => $s->description,
            'sort_order'     => $s->sort_order,
            'status'         => $s->status,
            'assigned_to_id' => $s->assigned_to_id,
            'assigned_name'  => $s->assignedTo?->name,
            'started_at'     => $s->started_at?->toIso8601String(),
            'completed_at'   => $s->completed_at?->toIso8601String(),
            'notes'          => $s->notes,
            'log'            => $s->log->map(fn($l) => [
                'id'         => $l->id,
                'user_id'    => $l->user_id,
                'message'    => $l->message,
                'created_at' => $l->created_at?->toIso8601String(),
            ])->values(),
            'created_at'     => $s->created_at->toIso8601String(),
            'updated_at'     => $s->updated_at->toIso8601String(),
        ];
    }
}
