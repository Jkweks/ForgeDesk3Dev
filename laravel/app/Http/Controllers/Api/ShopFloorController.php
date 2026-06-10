<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdWorkOrder;
use App\Models\FdWoStage;
use App\Models\FdUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShopFloorController extends Controller
{
    private const CYCLE = [
        'pending'      => 'in_progress',
        'in_progress'  => 'complete',
        'complete'     => 'pending',
        'blocked'      => 'pending',
        'not_required' => 'pending',
    ];

    public function workOrders(Request $request)
    {
        try {
            $wos = FdWorkOrder::with([
                'businessJob',
                'assignedUsers',
                'elevations.elevationType',
                'elevations.stages.assignedTo',
            ])
            ->where('archived', false)
            ->orderByRaw('priority IS NULL, priority ASC')
            ->get();

            $formatted = $wos->map(fn($wo) => $this->formatWo($wo));

            return response()->json(['work_orders' => $formatted]);
        } catch (\Exception $e) {
            Log::error('ShopFloorController@workOrders failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to load data'], 500);
        }
    }

    public function fabUsers()
    {
        return response()->json([
            'users' => FdUser::where('active', true)->orderBy('name')->get(['id', 'name', 'initials', 'role']),
        ]);
    }

    public function cycleStage(int $id)
    {
        try {
            $stage = FdWoStage::findOrFail($id);
            $next  = self::CYCLE[$stage->status] ?? 'pending';

            $stage->status = $next;
            if ($next === 'in_progress') {
                $stage->started_at   = now();
                $stage->completed_at = null;
            } elseif ($next === 'complete') {
                $stage->completed_at = now();
            } else {
                $stage->started_at   = null;
                $stage->completed_at = null;
            }
            $stage->save();

            return response()->json(['status' => $next]);
        } catch (\Exception $e) {
            Log::error('ShopFloorController@cycleStage failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to update stage'], 500);
        }
    }

    public function assignStage(Request $request, int $id)
    {
        try {
            $stage = FdWoStage::findOrFail($id);
            $stage->assigned_to_id = $request->user_id ?: null;
            $stage->save();
            return response()->json(['assigned' => $id]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to assign stage'], 500);
        }
    }

    private function formatWo(FdWorkOrder $wo): array
    {
        $job   = $wo->businessJob;
        $users = $wo->relationLoaded('assignedUsers') ? $wo->assignedUsers : collect();
        return [
            'id'             => $wo->id,
            'release_label'  => $job ? "{$job->job_number}-R{$wo->release_number}" : "R{$wo->release_number}",
            'job_name'       => $job?->job_name ?? '—',
            'job_number'     => $job?->job_number ?? '—',
            'date_issued'    => $wo->date_issued?->format('Y-m-d'),
            'priority'       => $wo->priority,
            'assigned_users' => $users->map(fn($u) => [
                'id'       => $u->id,
                'name'     => $u->name,
                'initials' => $u->initials,
            ])->values(),
            'elevations'    => $wo->elevations->map(fn($e) => [
                'id'             => $e->id,
                'elevation_tag'  => $e->elevation_tag,
                'scope'          => $e->scope,
                'date_requested' => $e->date_requested?->format('Y-m-d'),
                'date_completed' => $e->date_completed?->format('Y-m-d'),
                'elevation_type' => $e->elevationType ? [
                    'id'    => $e->elevationType->id,
                    'name'  => $e->elevationType->name,
                    'color' => $e->elevationType->color,
                ] : null,
                'stages' => $e->stages->map(fn($s) => [
                    'id'             => $s->id,
                    'name'           => $s->name,
                    'status'         => $s->status,
                    'sort_order'     => $s->sort_order,
                    'assigned_to_id' => $s->assigned_to_id,
                    'assigned_name'  => $s->assignedTo?->name,
                    'assigned_initials' => $s->assignedTo?->initials,
                    'started_at'     => $s->started_at?->toIso8601String(),
                    'completed_at'   => $s->completed_at?->toIso8601String(),
                ])->values(),
            ])->values(),
        ];
    }
}
