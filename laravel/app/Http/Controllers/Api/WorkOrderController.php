<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdWorkOrder;
use App\Models\FdWoStage;
use App\Models\FdStageTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkOrderController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = FdWorkOrder::with('assignedTo')
                ->withCount([
                    'stages',
                    'stages as stages_done'     => fn($q) => $q->where('status', 'complete'),
                    'stages as stages_active'   => fn($q) => $q->where('status', 'in_progress'),
                    'stages as stages_blocked'  => fn($q) => $q->where('status', 'blocked'),
                ]);

            $archived = $request->boolean('archived', false);
            $query->where('archived', $archived);

            if ($request->filled('type')) {
                $query->where('job_type', $request->type);
            }

            if ($request->filled('pm')) {
                $query->where('project_manager', $request->pm);
            }

            if ($request->filled('q')) {
                $q = $request->q;
                $query->where(function ($sub) use ($q) {
                    $sub->where('project_name', 'like', "%{$q}%")
                        ->orWhere('job_number', 'like', "%{$q}%")
                        ->orWhere('wo_number', 'like', "%{$q}%");
                });
            }

            if ($request->filled('material')) {
                $mat = $request->material;
                if ($mat === 'yes') {
                    $query->where('material_arrived', 'Yes');
                } elseif ($mat === 'sof') {
                    $query->where('material_arrived', 'SOF');
                } elseif ($mat === 'no') {
                    $query->whereNull('material_arrived');
                }
            }

            $workOrders = $query->orderByRaw('planned_complete_date IS NULL, planned_complete_date ASC')
                ->get()
                ->map(fn($wo) => $this->formatWo($wo));

            return response()->json(['work_orders' => $workOrders]);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@index failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to load work orders'], 500);
        }
    }

    public function show(int $id)
    {
        try {
            $wo = FdWorkOrder::with([
                'assignedTo',
                'stages.assignedTo',
                'stages.log',
            ])->findOrFail($id);

            $data = $this->formatWo($wo);
            $data['stages'] = $wo->stages->map(fn($s) => $this->formatStage($s))->values();

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@show failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['error' => 'Work order not found'], 404);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'project_name' => 'required|string|max:255',
            'wo_number'    => 'required|string|max:100',
            'job_type'     => 'required|in:SF,CW',
        ]);

        try {
            DB::beginTransaction();

            $wo = new FdWorkOrder($request->only([
                'project_name', 'job_number', 'wo_number', 'job_type', 'system',
                'date_received', 'planned_start_date', 'planned_complete_date',
                'requested_finish_date', 'joints', 'dr_fr_units', 'doors_units',
                'estimated_hours_override', 'material_arrived', 'cut_list_glazer',
                'notes', 'project_manager', 'assigned_to_id',
            ]));
            $wo->estimated_hours_calc = $wo->calcHours();
            $wo->save();

            // Seed stages from template
            $templates = FdStageTemplate::where('job_type', $wo->job_type)
                ->orderBy('sort_order')
                ->get();

            foreach ($templates as $tpl) {
                FdWoStage::create([
                    'work_order_id' => $wo->id,
                    'template_id'   => $tpl->id,
                    'name'          => $tpl->name,
                    'description'   => $tpl->description,
                    'sort_order'    => $tpl->sort_order,
                    'status'        => 'pending',
                ]);
            }

            DB::commit();

            return response()->json(['id' => $wo->id], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('WorkOrderController@store failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create work order'], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $wo = FdWorkOrder::findOrFail($id);
            $wo->fill($request->only([
                'project_name', 'job_number', 'wo_number', 'job_type', 'system',
                'date_received', 'planned_start_date', 'planned_complete_date',
                'requested_finish_date', 'joints', 'dr_fr_units', 'doors_units',
                'estimated_hours_override', 'material_arrived', 'cut_list_glazer',
                'notes', 'project_manager', 'assigned_to_id',
            ]));
            $wo->estimated_hours_calc = $wo->calcHours();
            $wo->save();

            return response()->json(['updated' => $id]);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to update work order'], 500);
        }
    }

    public function patch(Request $request, int $id)
    {
        try {
            $wo = FdWorkOrder::findOrFail($id);

            $allowed = [
                'project_name', 'job_number', 'wo_number', 'job_type', 'system',
                'date_received', 'planned_start_date', 'planned_complete_date',
                'requested_finish_date', 'joints', 'dr_fr_units', 'doors_units',
                'estimated_hours_override', 'material_arrived', 'cut_list_glazer',
                'notes', 'project_manager', 'assigned_to_id',
            ];

            $wo->fill($request->only($allowed));

            // Clear override if explicitly sent as null
            if ($request->has('estimated_hours_override') && is_null($request->estimated_hours_override)) {
                $wo->estimated_hours_override = null;
            }

            // Recompute if any qty field was touched
            if ($request->hasAny(['joints', 'dr_fr_units', 'doors_units', 'job_type'])) {
                $wo->estimated_hours_calc = $wo->calcHours();
            }

            $wo->save();

            return response()->json(['updated' => $id]);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@patch failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to patch work order'], 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            $wo = FdWorkOrder::findOrFail($id);
            $wo->archived    = true;
            $wo->archived_at = now();
            $wo->save();

            return response()->json(['archived' => $id]);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to archive work order'], 500);
        }
    }

    private function formatWo(FdWorkOrder $wo): array
    {
        return [
            'id'                       => $wo->id,
            'project_name'             => $wo->project_name,
            'job_number'               => $wo->job_number,
            'wo_number'                => $wo->wo_number,
            'job_type'                 => $wo->job_type,
            'system'                   => $wo->system,
            'date_received'            => $wo->date_received?->format('Y-m-d'),
            'planned_start_date'       => $wo->planned_start_date?->format('Y-m-d'),
            'planned_complete_date'    => $wo->planned_complete_date?->format('Y-m-d'),
            'requested_finish_date'    => $wo->requested_finish_date?->format('Y-m-d'),
            'joints'                   => $wo->joints,
            'dr_fr_units'              => $wo->dr_fr_units,
            'doors_units'              => $wo->doors_units,
            'estimated_hours_calc'     => $wo->estimated_hours_calc,
            'estimated_hours_override' => $wo->estimated_hours_override,
            'effective_hours'          => $wo->effectiveHours(),
            'material_arrived'         => $wo->material_arrived,
            'cut_list_glazer'          => $wo->cut_list_glazer,
            'notes'                    => $wo->notes,
            'project_manager'          => $wo->project_manager,
            'assigned_to_id'           => $wo->assigned_to_id,
            'assigned_name'            => $wo->assignedTo?->name,
            'assigned_initials'        => $wo->assignedTo?->initials,
            'stage_count'              => $wo->stages_count ?? 0,
            'stages_done'              => $wo->stages_done ?? 0,
            'stages_active'            => $wo->stages_active ?? 0,
            'stages_blocked'           => $wo->stages_blocked ?? 0,
            'archived'                 => $wo->archived,
            'archived_at'              => $wo->archived_at?->toIso8601String(),
            'created_at'               => $wo->created_at->toIso8601String(),
            'updated_at'               => $wo->updated_at->toIso8601String(),
        ];
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
