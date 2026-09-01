<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdUser;
use App\Models\FdWoStage;
use App\Services\StageGateService;
use Illuminate\Http\Request;

/**
 * The manager assignment board: every actionable, un-gated stage grouped by
 * operator (plus an "unassigned" bucket), each group ranked by effective
 * priority. Reassignment is done client-side via PATCH /work-order-stages/{id}.
 */
class WorkQueueController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'job_id'        => 'sometimes|integer',
            'work_order_id' => 'sometimes|integer',
        ]);

        $query = FdWoStage::query()
            ->actionable()
            ->with(['elevation.stages', 'elevation.workOrder.businessJob', 'assignedTo', 'assignees']);

        // Filter the board to one work order, or one job's work orders.
        if ($request->filled('work_order_id')) {
            $query->where('w.id', (int) $request->work_order_id);
        } elseif ($request->filled('job_id')) {
            $query->where('w.business_job_id', (int) $request->job_id);
        }

        $stages = $query
            ->orderByRaw("CASE WHEN fd_wo_stages.status = 'in_progress' THEN 0 ELSE 1 END")
            ->orderByRaw('w.priority IS NULL, w.priority ASC')
            ->orderByRaw('e.date_requested IS NULL, e.date_requested ASC')
            ->orderBy('fd_wo_stages.sort_order')
            ->orderBy('fd_wo_stages.id')
            ->get();

        // Keep gated stages on the board (dimmed on the client) so the workflow
        // can be planned/reassigned ahead of time — just annotate them.
        $gate = app(StageGateService::class);
        $stages->each(function (FdWoStage $s) use ($gate) {
            $blocker = $gate->blockingStageForLoaded($s, $s->elevation->stages);
            $s->setAttribute('is_gated', $blocker !== null);
            $s->setAttribute('blocking_stage_name', $blocker?->name);
        });

        $rows = $stages;

        $card = function (FdWoStage $s) {
            $wo  = $s->elevation->workOrder;
            $job = $wo?->businessJob;

            return [
                'stage_id'            => $s->id,
                'name'               => $s->name,
                'status'             => $s->status,
                'gated'              => (bool) $s->is_gated,
                'blocking_stage_name' => $s->blocking_stage_name,
                'assigned_to_id'     => $s->assigned_to_id,
                'assignee_ids'       => $s->assignees->pluck('id')->values(),
                'assignee_names'     => $s->assignees->pluck('name')->values(),
                'elevation_id'       => $s->elevation_id,
                'elevation_tag'      => $s->elevation->elevation_tag,
                'date_requested'     => $s->elevation->date_requested?->format('Y-m-d'),
                'work_order_id'      => $wo?->id,
                'business_job_id'    => $wo?->business_job_id,
                'release_label'      => $job ? "{$job->job_number}-R{$wo->release_number}" : "R{$wo?->release_number}",
                'job_name'           => $job?->job_name,
                'priority'           => $wo?->priority,
                'due_date'           => $wo?->due_date?->format('Y-m-d'),
            ];
        };

        // "Oldest waiting" is the oldest request date among stages that can
        // actually be started now (ignore gated ones).
        $oldest = fn ($group) => $group
            ->where('is_gated', false)
            ->map(fn ($s) => $s->elevation->date_requested?->format('Y-m-d'))
            ->filter()
            ->min();

        // A stage assigned to several operators lands in each of their columns.
        // Unassigned stages go to bucket 0.
        $grouped = $rows
            ->flatMap(function ($s) {
                $ids = $s->assignees->pluck('id');
                if ($ids->isEmpty() && $s->assigned_to_id) {
                    $ids = collect([$s->assigned_to_id]); // legacy row with no pivot yet
                }
                if ($ids->isEmpty()) {
                    $ids = collect([0]); // unassigned bucket
                }

                return $ids->map(fn ($uid) => ['uid' => (int) $uid, 'stage' => $s]);
            })
            ->groupBy('uid')
            ->map(fn ($pairs) => $pairs->pluck('stage'));

        $userDir = FdUser::whereIn('id', $rows
            ->flatMap(fn ($s) => $s->assignees->pluck('id')->push($s->assigned_to_id))
            ->filter()->unique())
            ->get()->keyBy('id');

        $operators = $grouped
            ->reject(fn ($g, $uid) => (int) $uid === 0)
            ->map(function ($g, $uid) use ($userDir, $card, $oldest) {
                $u = $userDir->get($uid);

                return [
                    'user' => [
                        'id'       => (int) $uid,
                        'name'     => $u->name ?? 'Unknown',
                        'initials' => $u->initials ?? '?',
                    ],
                    'count'                 => $g->count(),
                    'ready_count'           => $g->where('is_gated', false)->count(),
                    'oldest_date_requested' => $oldest($g),
                    'stages'                => $g->map($card)->values(),
                ];
            })
            ->sortBy('user.name')
            ->values();

        $unassignedGroup = $grouped->get(0, collect());

        return response()->json([
            'operators'  => $operators,
            'unassigned' => [
                'count'                 => $unassignedGroup->count(),
                'ready_count'           => $unassignedGroup->where('is_gated', false)->count(),
                'oldest_date_requested' => $oldest($unassignedGroup),
                'stages'                => $unassignedGroup->map($card)->values(),
            ],
            // Every active fab user, so the board can show empty columns to drop onto.
            'fab_users'  => FdUser::where('active', true)->orderBy('name')->get(['id', 'name', 'initials']),
        ]);
    }
}
