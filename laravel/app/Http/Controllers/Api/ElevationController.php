<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdWoElevation;
use App\Models\FdWoStage;
use App\Models\FdStageTemplate;
use App\Models\FdWorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ElevationController extends Controller
{
    public function index(int $workOrderId)
    {
        $wo = FdWorkOrder::findOrFail($workOrderId);
        $elevations = $wo->elevations()
            ->with(['elevationType', 'completedBy', 'stages.assignedTo'])
            ->get()
            ->map(fn($e) => $this->formatElevation($e));

        return response()->json(['elevations' => $elevations]);
    }

    public function store(Request $request, int $workOrderId)
    {
        $request->validate([
            'elevation_tag'     => 'required|string|max:100',
            'elevation_type_id' => 'nullable|integer|exists:fd_elevation_types,id',
        ]);

        FdWorkOrder::findOrFail($workOrderId);

        try {
            DB::beginTransaction();

            $elevation = FdWoElevation::create([
                'work_order_id'     => $workOrderId,
                'elevation_type_id' => $request->elevation_type_id,
                'elevation_tag'     => $request->elevation_tag,
                'quantity'          => $request->quantity ?? 1,
                'date_requested'    => $request->date_requested,
                'notes'             => $request->notes,
            ]);

            // Auto-seed stages from template for this elevation type
            if ($request->elevation_type_id) {
                $templates = FdStageTemplate::where('elevation_type_id', $request->elevation_type_id)
                    ->orderBy('sort_order')
                    ->get();

                foreach ($templates as $tpl) {
                    FdWoStage::create([
                        'elevation_id' => $elevation->id,
                        'work_order_id' => null,
                        'template_id'  => $tpl->id,
                        'name'         => $tpl->name,
                        'description'  => $tpl->description,
                        'sort_order'   => $tpl->sort_order,
                        'status'       => 'pending',
                    ]);
                }
            }

            DB::commit();

            $elevation->load(['elevationType', 'completedBy', 'stages.assignedTo']);
            return response()->json($this->formatElevation($elevation), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ElevationController@store failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create elevation'], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $elevation = FdWoElevation::findOrFail($id);
            $elevation->fill($request->only([
                'elevation_type_id', 'elevation_tag', 'quantity',
                'date_requested', 'date_completed', 'completed_by_id', 'notes',
            ]));
            $elevation->save();

            $elevation->load(['elevationType', 'completedBy', 'stages.assignedTo']);
            return response()->json($this->formatElevation($elevation));
        } catch (\Exception $e) {
            Log::error('ElevationController@update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to update elevation'], 500);
        }
    }

    public function destroy(int $id)
    {
        $elevation = FdWoElevation::findOrFail($id);
        $elevation->delete();
        return response()->json(['deleted' => $id]);
    }

    private function formatElevation(FdWoElevation $e): array
    {
        $stages = $e->relationLoaded('stages') ? $e->stages : $e->stages()->with('assignedTo')->get();
        $stageCount  = $stages->count();
        $stagesDone  = $stages->where('status', 'complete')->count();
        $stagesActive = $stages->where('status', 'in_progress')->count();
        $stagesBlocked = $stages->where('status', 'blocked')->count();

        return [
            'id'                => $e->id,
            'work_order_id'     => $e->work_order_id,
            'elevation_type_id' => $e->elevation_type_id,
            'elevation_type'    => $e->elevationType ? [
                'id'    => $e->elevationType->id,
                'name'  => $e->elevationType->name,
                'color' => $e->elevationType->color,
            ] : null,
            'elevation_tag'     => $e->elevation_tag,
            'quantity'          => $e->quantity,
            'date_requested'    => $e->date_requested?->format('Y-m-d'),
            'date_completed'    => $e->date_completed?->format('Y-m-d'),
            'completed_by_id'   => $e->completed_by_id,
            'completed_by_name' => $e->completedBy?->name,
            'notes'             => $e->notes,
            'stage_count'       => $stageCount,
            'stages_done'       => $stagesDone,
            'stages_active'     => $stagesActive,
            'stages_blocked'    => $stagesBlocked,
            'stages'            => $stages->map(fn($s) => [
                'id'           => $s->id,
                'name'         => $s->name,
                'status'       => $s->status,
                'sort_order'   => $s->sort_order,
                'assigned_name'=> $s->assignedTo?->name,
                'started_at'   => $s->started_at?->toIso8601String(),
                'completed_at' => $s->completed_at?->toIso8601String(),
            ])->values(),
            'created_at'        => $e->created_at->toIso8601String(),
            'updated_at'        => $e->updated_at->toIso8601String(),
        ];
    }
}
