<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdScheduleEntry;
use App\Models\FdUser;
use App\Models\FdWoStage;
use App\Services\StageGateService;
use Illuminate\Http\Request;

/**
 * The manager assignment board: every actionable, un-gated stage grouped by
 * operator (plus an "unassigned" bucket), each group ranked by effective
 * priority. Cards are job-level (work order + step name) and carry every
 * elevation's stage id; reassignment is done client-side via
 * PATCH /work-order-stages/{id} for each of them.
 */
class WorkQueueController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'job_id' => 'sometimes|integer',
            'work_order_id' => 'sometimes|integer',
        ]);

        $query = FdWoStage::query()
            ->actionable()
            ->with(['elevation.stages', 'elevation.workOrder.businessJob', 'elevation.elevationType', 'elevation.doorFrameConfiguration', 'assignedTo', 'assignees']);

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
            $configReason = $gate->blockingConfigurationReasonFor($s);
            $s->setAttribute('is_gated', $blocker !== null || $configReason !== null);
            $s->setAttribute('blocking_stage_name', $blocker?->name ?? $configReason);
        });

        $rows = $stages;

        // Work content per work order (minutes), for sizing a bar when it's dropped on the schedule
        $workOrders = $stages->map(fn ($s) => $s->elevation->workOrder)->filter()->unique('id');
        $workOrders->load(['elevations.stages', 'elevations.templateSet']);
        $estimates = $workOrders->mapWithKeys(fn ($wo) => [$wo->id => $wo->estimateMinutes()['effective']]);

        // One card per work order + step name: the elevations' stages are assigned
        // together here, but each keeps its own row so it is still completed
        // individually on the shop floor / work order modals.
        $card = function ($group) use ($estimates) {
            $s = $group->first();
            $wo = $s->elevation->workOrder;
            $job = $wo?->businessJob;
            $gated = $group->every(fn ($g) => $g->is_gated);
            $tags = $group->map(fn ($g) => $g->elevation->elevation_tag)->unique()->values();

            return [
                'stage_id' => $s->id,
                'stage_ids' => $group->pluck('id')->values(),
                // Elevation type (scope: storefront, door, frame...) of each stage, aligned with stage_ids
                'stage_scopes' => $group->map(fn ($g) => $g->elevation->elevationType?->name ?? 'Other')->values(),
                'name' => $s->name,
                'status' => $group->contains('status', 'in_progress') ? 'in_progress' : $s->status,
                'gated' => $gated,
                'blocking_stage_name' => $gated ? $s->blocking_stage_name : null,
                'assigned_to_id' => $s->assigned_to_id,
                'assignee_ids' => $s->assignees->pluck('id')->values(),
                'assignee_names' => $s->assignees->pluck('name')->values(),
                'elevation_id' => $s->elevation_id,
                'elevation_tag' => $tags->count() === 1 ? $tags->first() : "{$tags->count()} elevations",
                'elevation_tags' => $tags,
                'elevation_count' => $group->count(),
                'date_requested' => $group->map(fn ($g) => $g->elevation->date_requested?->format('Y-m-d'))->filter()->min(),
                'work_order_id' => $wo?->id,
                'business_job_id' => $wo?->business_job_id,
                'release_label' => $job ? "{$job->job_number}-{$wo->release_token}" : "{$wo?->release_token}",
                'release_token' => $wo?->release_token,
                'job_number' => $job?->job_number,
                'job_name' => $job?->job_name,
                'priority' => $wo?->priority,
                'estimated_minutes' => $wo ? $estimates->get($wo->id) : null,
                'due_date' => $wo?->due_date?->format('Y-m-d'),
                'planned_start_date' => $wo?->planned_start_date?->format('Y-m-d'),
                'planned_completion_date' => $wo?->planned_completion_date?->format('Y-m-d'),
                'planned_start_quarter' => $wo?->planned_start_quarter ?? 0,
                'planned_completion_quarter' => $wo?->planned_completion_quarter ?? 3,
            ];
        };

        // Collapse an operator's stages to job-level groups (work order + step
        // name), keeping the board's ranking order for the first of each.
        $collapse = fn ($stages) => $stages
            ->groupBy(fn ($s) => $s->elevation->work_order_id.'|'.mb_strtolower($s->name))
            ->values();

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
            ->map(function ($g, $uid) use ($userDir, $card, $oldest, $collapse) {
                $u = $userDir->get($uid);

                return [
                    'user' => [
                        'id' => (int) $uid,
                        'name' => $u->name ?? 'Unknown',
                        'initials' => $u->initials ?? '?',
                    ],
                    'count' => $collapse($g)->count(),
                    'ready_count' => $collapse($g)->filter(fn ($c) => $c->contains('is_gated', false))->count(),
                    'oldest_date_requested' => $oldest($g),
                    'stages' => $collapse($g)->map($card)->values(),
                ];
            })
            ->sortBy('user.name')
            ->values();

        $unassignedGroup = $grouped->get(0, collect());

        return response()->json([
            'operators' => $operators,
            'unassigned' => [
                'count' => $collapse($unassignedGroup)->count(),
                'ready_count' => $collapse($unassignedGroup)->filter(fn ($c) => $c->contains('is_gated', false))->count(),
                'oldest_date_requested' => $oldest($unassignedGroup),
                'stages' => $collapse($unassignedGroup)->map($card)->values(),
            ],
            // Every active fab user, so the board can show empty columns to drop onto.
            // Assists and notes on operator rows (recent and upcoming only)
            'schedule_entries' => FdScheduleEntry::with('workOrder.businessJob:id,job_number,job_name')
                ->where('end_date', '>=', today()->subDays(45))
                ->get()
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'user_id' => $e->user_id,
                    'work_order_id' => $e->work_order_id,
                    'label' => $e->workOrder ? trim(($e->workOrder->businessJob?->job_name ?? '').' '.($e->workOrder->release_token ? "({$e->workOrder->release_token})" : '')) : null,
                    'note' => $e->note,
                    'start_date' => $e->start_date->format('Y-m-d'),
                    'end_date' => $e->end_date->format('Y-m-d'),
                    'start_quarter' => $e->start_quarter,
                    'end_quarter' => $e->end_quarter,
                ])->values(),
            'fab_users' => FdUser::where('active', true)->orderBy('name')->get(['id', 'name', 'initials']),
        ]);
    }
}
