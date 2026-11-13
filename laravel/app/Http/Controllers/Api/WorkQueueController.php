<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdScheduleEntry;
use App\Models\FdUser;
use App\Models\FdStageLog;
use App\Models\FdWoElevation;
use App\Models\FdWoOperatorSchedule;
use App\Models\FdWorkOrder;
use App\Models\FdWoStage;
use App\Services\StageGateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The manager assignment board: every actionable, un-gated stage grouped by
 * operator (plus an "unassigned" bucket), each group ranked by effective
 * priority. Cards are job-level (work order + step name) and carry every
 * elevation's stage id. Reassignment goes through reassign(), which edits each
 * stage's own assignee set (so co-assignees are never clobbered); scheduling a
 * work order on one operator's row goes through setOperatorSchedule().
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
                // Everyone on any of the card's stages (elevations of one step can be split between operators)
                'assignee_ids' => $group->flatMap(fn ($g) => $g->assignees->pluck('id'))->unique()->values(),
                'assignee_names' => $group->flatMap(fn ($g) => $g->assignees->pluck('name'))->unique()->values(),
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

        // Steps are optional: open elevations with no stages at all get one
        // card per work order showing just a Complete button.
        $stagelessCards = $this->stagelessCards($request);

        return response()->json([
            'operators' => $operators,
            'unassigned' => [
                'count' => $collapse($unassignedGroup)->count() + $stagelessCards->count(),
                'ready_count' => $collapse($unassignedGroup)->filter(fn ($c) => $c->contains('is_gated', false))->count() + $stagelessCards->count(),
                'oldest_date_requested' => collect([$oldest($unassignedGroup), $stagelessCards->pluck('date_requested')->filter()->min()])->filter()->min(),
                'stages' => $collapse($unassignedGroup)->map($card)->values()->concat($stagelessCards)->values(),
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
            // Per-operator planning windows; an operator without one uses the work order's planned dates
            'operator_windows' => FdWoOperatorSchedule::whereIn('work_order_id', $workOrders->pluck('id'))
                ->get()
                ->map(fn ($w) => [
                    'work_order_id' => $w->work_order_id,
                    'user_id' => $w->user_id,
                    'start_date' => $w->start_date->format('Y-m-d'),
                    'end_date' => $w->end_date->format('Y-m-d'),
                    'start_quarter' => $w->start_quarter,
                    'end_quarter' => $w->end_quarter,
                ])->values(),
            'fab_users' => FdUser::where('active', true)->orderBy('name')->get(['id', 'name', 'initials']),
        ]);
    }

    /**
     * Change who is on a set of stages without touching their co-assignees.
     *
     * Body: { stage_ids: [], remove_user_id?, add_user_ids?: [], set_user_ids?: [] }
     * `set_user_ids` replaces each stage's assignees outright (empty = unassign).
     * Otherwise each stage keeps its own assignees, minus `remove_user_id`, plus
     * `add_user_ids` — so moving a shared step from Alice to Bob leaves Carol on it.
     * When one operator fully hands a work order to another, their schedule
     * window on it goes with it.
     */
    public function reassign(Request $request)
    {
        $data = $request->validate([
            'stage_ids' => 'required|array|min:1',
            'stage_ids.*' => 'integer',
            'remove_user_id' => 'nullable|integer',
            'add_user_ids' => 'sometimes|array',
            'add_user_ids.*' => 'integer|exists:fd_users,id',
            'set_user_ids' => 'sometimes|array',
            'set_user_ids.*' => 'integer|exists:fd_users,id',
        ]);

        $remove = (int) ($data['remove_user_id'] ?? 0);
        $add = collect($data['add_user_ids'] ?? [])->map(fn ($v) => (int) $v);
        $set = array_key_exists('set_user_ids', $data) ? collect($data['set_user_ids'])->map(fn ($v) => (int) $v) : null;
        $actor = trim($request->user()?->name ?? 'Office user');
        $names = FdUser::pluck('name', 'id');
        $label = fn ($ids) => $ids->map(fn ($id) => $names[$id] ?? 'Unknown')->sort()->join(', ') ?: 'Unassigned';

        $updated = 0;
        DB::transaction(function () use ($data, $remove, $add, $set, $actor, $label, &$updated) {
            $stages = FdWoStage::with(['assignees:id', 'elevation:id,work_order_id'])
                ->whereIn('id', $data['stage_ids'])
                ->whereNotIn('status', FdWoStage::TERMINAL)
                ->get();

            $woIds = $stages->map(fn ($s) => $s->elevation?->work_order_id ?? $s->work_order_id)->filter()->unique();
            // Snapshot before the stage writes prune the outgoing operator's windows
            $windows = $remove
                ? FdWoOperatorSchedule::whereIn('work_order_id', $woIds)->where('user_id', $remove)->get()->keyBy('work_order_id')
                : collect();

            foreach ($stages as $s) {
                $current = $s->assignees->pluck('id');
                if ($current->isEmpty() && $s->assigned_to_id) {
                    $current = collect([$s->assigned_to_id]); // legacy row with no pivot yet
                }
                $current = $current->map(fn ($v) => (int) $v)->values();
                $new = ($set ?? $current->reject(fn ($id) => $id === $remove)->concat($add))->unique()->values();

                if ($current->sort()->values()->all() === $new->sort()->values()->all()) {
                    continue;
                }

                $s->syncAssignees($new->all());
                FdStageLog::create([
                    'stage_id' => $s->id,
                    'user_id' => null,
                    'message' => "Reassigned from {$label($current)} to {$label($new)} via Work Queue by {$actor}",
                ]);
                $updated++;
            }

            // A straight hand-over (one operator out, one in) keeps the bar where it was on the schedule
            if ($remove && $add->count() === 1) {
                foreach ($windows as $woId => $w) {
                    $gone = ! FdWoOperatorSchedule::where('work_order_id', $woId)->where('user_id', $remove)->exists();
                    if ($gone) {
                        FdWoOperatorSchedule::firstOrCreate(
                            ['work_order_id' => $woId, 'user_id' => $add->first()],
                            ['start_date' => $w->start_date, 'end_date' => $w->end_date, 'start_quarter' => $w->start_quarter, 'end_quarter' => $w->end_quarter],
                        );
                        FdWoOperatorSchedule::syncEnvelope((int) $woId);
                    }
                }
            }
        });

        return response()->json(['updated' => $updated]);
    }

    /**
     * Place a work order on one operator's row of the schedule. Other operators
     * on the work order keep their place: anyone still following the work
     * order's planned dates is pinned to them first, then the work order's
     * planned dates become the envelope of every operator's window.
     */
    public function setOperatorSchedule(Request $request, int $id, int $userId)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_quarter' => 'sometimes|integer|between:0,3',
            'end_quarter' => 'sometimes|integer|between:0,3',
        ]);

        $wo = FdWorkOrder::findOrFail($id);
        FdUser::findOrFail($userId);

        DB::transaction(function () use ($wo, $userId, $data) {
            $end = $wo->planned_completion_date ?? $wo->due_date ?? $wo->planned_start_date;
            if ($end) {
                $start = $wo->planned_start_date ?? $end;
                $have = $wo->operatorSchedules()->pluck('user_id');
                foreach (FdWoOperatorSchedule::assignedUserIds($wo->id)->diff($have)->reject(fn ($u) => $u === $userId) as $other) {
                    FdWoOperatorSchedule::create([
                        'work_order_id' => $wo->id,
                        'user_id' => $other,
                        'start_date' => $start,
                        'end_date' => $end,
                        'start_quarter' => $wo->planned_start_date ? ($wo->planned_start_quarter ?? 0) : 0,
                        'end_quarter' => $wo->planned_completion_date ? ($wo->planned_completion_quarter ?? 3) : 3,
                    ]);
                }
            }

            FdWoOperatorSchedule::updateOrCreate(
                ['work_order_id' => $wo->id, 'user_id' => $userId],
                [
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'start_quarter' => $data['start_quarter'] ?? 0,
                    'end_quarter' => $data['end_quarter'] ?? 3,
                ],
            );

            FdWoOperatorSchedule::syncEnvelope($wo->id);
        });

        return response()->json(['updated' => $id]);
    }

    /**
     * Cards for open elevations that have no steps. One per work order; the
     * client shows a Complete button that closes each listed elevation.
     */
    private function stagelessCards(Request $request)
    {
        $query = FdWoElevation::query()
            ->whereNull('date_completed')
            ->whereDoesntHave('stages')
            ->whereHas('workOrder', function ($q) use ($request) {
                $q->where('archived', false);
                if ($request->filled('work_order_id')) {
                    $q->where('id', (int) $request->work_order_id);
                } elseif ($request->filled('job_id')) {
                    $q->where('business_job_id', (int) $request->job_id);
                }
            })
            ->with(['workOrder.businessJob', 'elevationType']);

        return $query->get()
            ->groupBy('work_order_id')
            ->map(function ($group) {
                $first = $group->first();
                $wo = $first->workOrder;
                $job = $wo->businessJob;
                $tags = $group->pluck('elevation_tag')->unique()->values();

                return [
                    'stage_id' => null,
                    'stage_ids' => [],
                    'stageless' => true,
                    'elevation_ids' => $group->pluck('id')->values(),
                    'stage_scopes' => [],
                    'name' => 'No steps',
                    'status' => 'pending',
                    'gated' => false,
                    'blocking_stage_name' => null,
                    'assigned_to_id' => null,
                    'assignee_ids' => [],
                    'assignee_names' => [],
                    'elevation_id' => $first->id,
                    'elevation_tag' => $tags->count() === 1 ? $tags->first() : "{$tags->count()} elevations",
                    'elevation_tags' => $tags,
                    'elevation_count' => $group->count(),
                    'date_requested' => $group->map(fn ($g) => $g->date_requested?->format('Y-m-d'))->filter()->min(),
                    'work_order_id' => $wo->id,
                    'business_job_id' => $wo->business_job_id,
                    'release_label' => $job ? "{$job->job_number}-{$wo->release_token}" : "{$wo->release_token}",
                    'release_token' => $wo->release_token,
                    'job_number' => $job?->job_number,
                    'job_name' => $job?->job_name,
                    'priority' => $wo->priority,
                    'estimated_minutes' => null,
                    'due_date' => $wo->due_date?->format('Y-m-d'),
                    'planned_start_date' => $wo->planned_start_date?->format('Y-m-d'),
                    'planned_completion_date' => $wo->planned_completion_date?->format('Y-m-d'),
                    'planned_start_quarter' => $wo->planned_start_quarter ?? 0,
                    'planned_completion_quarter' => $wo->planned_completion_quarter ?? 3,
                ];
            })
            ->sortBy([['priority', 'asc']])
            ->values();
    }
}
