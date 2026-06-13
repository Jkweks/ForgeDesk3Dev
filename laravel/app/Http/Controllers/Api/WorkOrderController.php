<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdWorkOrder;
use App\Models\BusinessJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkOrderController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = FdWorkOrder::with(['businessJob', 'assignedUsers'])
                ->withCount([
                    'elevations',
                    'elevations as elevations_complete' => fn($q) => $q->whereNotNull('date_completed'),
                ]);

            $archived = $request->boolean('archived', false);
            $query->where('archived', $archived);

            if ($request->filled('job_id')) {
                $query->where('business_job_id', $request->job_id);
            }

            if ($request->filled('q')) {
                $q = $request->q;
                $query->whereHas('businessJob', function ($sub) use ($q) {
                    $sub->where('job_number', 'like', "%{$q}%")
                        ->orWhere('job_name', 'like', "%{$q}%");
                });
            }

            if ($request->filled('material')) {
                $mat = $request->material;
                if ($mat === 'in_shop') {
                    $query->where('material_delivery', 'In Shop');
                } elseif ($mat === 'sof') {
                    $query->where('material_delivery', 'SOF');
                } elseif ($mat === 'pending') {
                    $query->whereNull('material_delivery');
                }
            }

            $workOrders = $query
                ->orderByRaw('priority IS NULL, priority ASC')
                ->orderBy('date_issued', 'desc')
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
                'businessJob',
                'assignedUsers',
                'drawings',
                'steps.completedBy',
                'elevations.elevationType',
                'elevations.completedBy',
                'elevations.stages.assignedTo',
                'elevations.stages.completedBy',
            ])->findOrFail($id);

            $data = $this->formatWo($wo);
            $data['steps'] = $wo->steps->map(fn($s) => [
                'id'                => $s->id,
                'work_order_id'     => $s->work_order_id,
                'name'              => $s->name,
                'sort_order'        => $s->sort_order,
                'status'            => $s->status,
                'completed_by_id'   => $s->completed_by_id,
                'completed_by_name' => $s->completedBy?->name,
                'completed_at'      => $s->completed_at?->toIso8601String(),
            ])->values();
            $data['drawings']   = $wo->drawings->map(fn($d) => [
                'id'            => $d->id,
                'original_name' => $d->original_name,
                'file_size'     => $d->file_size,
                'file_mime'     => $d->file_mime,
                'download_url'  => "/api/v1/work-orders/{$wo->id}/drawings/{$d->id}/download",
                'created_at'    => $d->created_at->toIso8601String(),
            ])->values();
            $data['elevations'] = $wo->elevations->map(fn($e) => $this->formatElevation($e))->values();

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@show failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['error' => 'Work order not found'], 404);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'business_job_id' => 'required|integer|exists:business_jobs,id',
        ]);

        try {
            $nextRelease  = FdWorkOrder::where('business_job_id', $request->business_job_id)->max('release_number') + 1;
            $nextPriority = FdWorkOrder::where('archived', false)->max('priority') + 1;

            $wo = FdWorkOrder::create([
                'business_job_id'   => $request->business_job_id,
                'release_number'    => $nextRelease,
                'date_issued'       => $request->date_issued,
                'material_delivery' => $request->material_delivery,
                'notes'             => $request->notes,
                'priority'          => $nextPriority,
            ]);

            return response()->json(['id' => $wo->id, 'release_number' => $wo->release_number], 201);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@store failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create work order'], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $wo = FdWorkOrder::findOrFail($id);
            $wo->fill($request->only(['date_issued', 'material_delivery', 'notes', 'priority']));
            $wo->save();

            return response()->json(['updated' => $id]);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to update work order'], 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            $wo = FdWorkOrder::findOrFail($id);
            $wo->archived = true;
            $wo->save();

            return response()->json(['archived' => $id]);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to archive work order'], 500);
        }
    }

    public function updateAssignments(Request $request, int $id)
    {
        try {
            $wo = FdWorkOrder::findOrFail($id);
            $userIds = $request->input('user_ids', []);
            $wo->assignedUsers()->sync($userIds);
            return response()->json(['updated' => $id]);
        } catch (\Exception $e) {
            Log::error('WorkOrderController@updateAssignments failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to update assignments'], 500);
        }
    }

    private function formatWo(FdWorkOrder $wo): array
    {
        $job = $wo->relationLoaded('businessJob') ? $wo->businessJob : $wo->businessJob()->first();

        $users = $wo->relationLoaded('assignedUsers') ? $wo->assignedUsers : collect();

        return [
            'id'                  => $wo->id,
            'business_job_id'     => $wo->business_job_id,
            'release_number'      => $wo->release_number,
            'release_label'       => $job ? "{$job->job_number}-R{$wo->release_number}" : "R{$wo->release_number}",
            'date_issued'         => $wo->date_issued?->format('Y-m-d'),
            'material_delivery'   => $wo->material_delivery,
            'notes'               => $wo->notes,
            'archived'            => $wo->archived,
            'priority'            => $wo->priority,
            'elevation_count'     => $wo->elevations_count ?? 0,
            'elevations_complete' => $wo->elevations_complete ?? 0,
            'assigned_users'      => $users->map(fn($u) => [
                'id'       => $u->id,
                'name'     => $u->name,
                'initials' => $u->initials,
            ])->values(),
            'job'                 => $job ? [
                'id'              => $job->id,
                'job_number'      => $job->job_number,
                'job_name'        => $job->job_name,
                'project_manager' => $job->project_manager,
                'division'        => substr($job->job_number ?? '', 0, 1) ?: '—',
            ] : null,
            'created_at'          => $wo->created_at->toIso8601String(),
            'updated_at'          => $wo->updated_at->toIso8601String(),
        ];
    }

    private function formatElevation($e): array
    {
        $stages = $e->relationLoaded('stages') ? $e->stages : collect();

        return [
            'id'                => $e->id,
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
            'scope'             => $e->scope ?? 'assemble',
            'stage_count'       => $stages->count(),
            'stages_done'       => $stages->whereIn('status', ['complete', 'not_required'])->count(),
            'stages_active'     => $stages->where('status', 'in_progress')->count(),
            'stages_blocked'    => $stages->where('status', 'blocked')->count(),
            'stages'            => $stages->map(fn($s) => [
                'id'                => $s->id,
                'name'              => $s->name,
                'status'            => $s->status,
                'sort_order'        => $s->sort_order,
                'assigned_name'     => $s->assignedTo?->name,
                'completed_by_id'   => $s->completed_by_id,
                'completed_by_name' => $s->completedBy?->name,
                'started_at'        => $s->started_at?->toIso8601String(),
                'completed_at'      => $s->completed_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
