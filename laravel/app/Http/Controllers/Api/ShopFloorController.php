<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\StageGatedException;
use App\Http\Controllers\Controller;
use App\Models\FdStageLog;
use App\Models\FdUser;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Models\FdWoStage;
use App\Services\StageGateService;
use App\Services\StageOverrideResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ShopFloorController extends Controller
{
    private const CYCLE = [
        'pending' => 'in_progress',
        'in_progress' => 'complete',
        'complete' => 'pending',
        'blocked' => 'pending',
        'not_required' => 'pending',
        'on_hold' => 'pending',
    ];

    public function __construct(
        private StageGateService $gate,
        private StageOverrideResolver $overrides,
    ) {}

    public function workOrders(Request $request)
    {
        try {
            $wos = FdWorkOrder::with([
                'businessJob',
                'assignedUsers',
                'elevations.elevationType',
                'elevations.completedBy',
                'elevations.stages.assignedTo',
                'elevations.stages.assignees',
                'elevations.stages.completedBy',
            ])
                ->where('archived', false)
                ->orderByRaw('priority IS NULL, priority ASC')
                ->get();

            $formatted = $wos->map(fn ($wo) => $this->formatWo($wo));

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

    public function pinLogin(Request $request)
    {
        $request->validate(['pin' => 'required|string']);
        $users = FdUser::where('active', true)->whereNotNull('fab_pin')->get();
        foreach ($users as $user) {
            if (Hash::check($request->pin, $user->fab_pin)) {
                return response()->json([
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'initials' => $user->initials,
                    'role' => $user->role,
                ]);
            }
        }

        return response()->json(['error' => 'Invalid PIN'], 401);
    }

    public function cycleStage(Request $request, int $id)
    {
        $stage = FdWoStage::findOrFail($id);
        $next = self::CYCLE[$stage->status] ?? 'pending';

        // Gate check runs OUTSIDE the try/catch so StageGatedException renders
        // itself as a 422 instead of being swallowed as a generic 500.
        $resolution = $this->overrides->resolve($request);
        $this->gate->guardStageTransition(
            $stage,
            $next,
            $resolution['allowed'],
            $this->overrides->stageLogger($stage, $resolution),
        );

        try {
            $stage->status = $next;
            if ($next === 'in_progress') {
                $stage->started_at = now();
                $stage->completed_at = null;
                $stage->completed_by_id = null;
            } elseif ($next === 'complete') {
                $stage->completed_at = now();
                $stage->completed_by_id = $request->input('fab_user_id') ?: null;
            } else {
                $stage->started_at = null;
                $stage->completed_at = null;
                $stage->completed_by_id = null;
            }
            $stage->save();

            return response()->json(['status' => $next]);
        } catch (\Exception $e) {
            Log::error('ShopFloorController@cycleStage failed', ['id' => $id, 'message' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to update stage'], 500);
        }
    }

    /**
     * Put a single stage on hold, or take it back off hold, from the kiosk.
     * Restricted to the on_hold <-> pending pair — advancing a stage still
     * goes through cycleStage() so the sequential gate is enforced.
     *
     * Body: { status: 'on_hold'|'pending', fab_user_id? }
     */
    public function setStageStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['on_hold', 'pending'])],
            'fab_user_id' => 'nullable|integer|exists:fd_users,id',
        ]);

        try {
            $stage = FdWoStage::findOrFail($id);
            $fabUser = ($data['fab_user_id'] ?? null) ? FdUser::find($data['fab_user_id']) : null;

            $stage->status = $data['status'];
            $stage->started_at = null;
            $stage->completed_at = null;
            $stage->completed_by_id = null;
            $stage->save();

            FdStageLog::create([
                'stage_id' => $stage->id,
                'user_id' => $fabUser?->id,
                'message' => ($data['status'] === 'on_hold' ? 'Put on hold' : 'Taken off hold')
                    .' from the shop floor'.($fabUser ? " by {$fabUser->name}" : ''),
            ]);

            return response()->json(['status' => $stage->status]);
        } catch (\Exception $e) {
            Log::error('ShopFloorController@setStageStatus failed', ['id' => $id, 'message' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to update stage'], 500);
        }
    }

    public function updateElevation(Request $request, int $id)
    {
        try {
            $elevation = FdWoElevation::findOrFail($id);
            // Only allow completion fields from the public shop route
            if ($request->has('date_completed')) {
                $elevation->date_completed = $request->date_completed ?: null;
                $elevation->completed_by_id = $request->input('completed_by_id') ?: null;
            }
            $elevation->save();

            return response()->json(['updated' => $id]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update elevation'], 500);
        }
    }

    public function bulkCompleteStages(Request $request, int $id)
    {
        $elevation = FdWoElevation::with('stages')->findOrFail($id);
        $resolution = $this->overrides->resolve($request);
        $fabUserId = $request->input('fab_user_id') ?: null;

        try {
            $updated = 0;

            DB::transaction(function () use ($elevation, $resolution, $fabUserId, &$updated) {
                // Complete in order so each stage's blocking predecessors are
                // already terminal by the time we reach it. A 'blocked'/'on_hold'
                // predecessor is skipped by this sweep, so a later stage that
                // depends on it will trip the gate and roll the whole batch back.
                $stages = $elevation->stages
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->sortBy('sort_order');

                foreach ($stages as $stage) {
                    $this->gate->guardStageTransition(
                        $stage,
                        'complete',
                        $resolution['allowed'],
                        $this->overrides->stageLogger($stage, $resolution),
                    );

                    $stage->status = 'complete';
                    $stage->completed_at = now();
                    $stage->completed_by_id = $fabUserId;
                    $stage->save();
                    $updated++;
                }
            });

            return response()->json(['updated' => $updated]);
        } catch (StageGatedException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('ShopFloorController@bulkCompleteStages failed', ['id' => $id, 'message' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to complete stages'], 500);
        }
    }

    /**
     * Complete every instance of a named stage across all elevations of a work
     * order — the kiosk press-and-hold "bulk complete step" action. Mirrors
     * WorkOrderStageController@bulkComplete, including closing any elevation whose
     * last outstanding stage this sweep completes.
     *
     * Body: { stage_name, fab_user_id?, override? }
     */
    public function bulkCompleteWoStage(Request $request, int $id)
    {
        $data = $request->validate([
            'stage_name' => 'required|string|max:255',
            'fab_user_id' => 'nullable|integer|exists:fd_users,id',
            'override' => 'sometimes|boolean',
        ]);

        $wo = FdWorkOrder::with('elevations.stages')->findOrFail($id);
        $resolution = $this->overrides->resolve($request);
        $actor = $resolution['actor_label'] ?: 'Shop floor';
        $fabUserId = $data['fab_user_id'] ?? null;
        $target = mb_strtolower($data['stage_name']);

        try {
            $updated = 0;
            $elevationsClosed = 0;

            DB::transaction(function () use ($wo, $target, $fabUserId, $resolution, $actor, &$updated, &$elevationsClosed) {
                foreach ($wo->elevations as $elevation) {
                    $stages = $elevation->stages
                        ->filter(fn ($s) => mb_strtolower($s->name) === $target)
                        ->whereIn('status', ['pending', 'in_progress'])
                        ->sortBy('sort_order');

                    foreach ($stages as $stage) {
                        $this->gate->guardStageTransition(
                            $stage,
                            'complete',
                            $resolution['allowed'],
                            $this->overrides->stageLogger($stage, $resolution),
                        );

                        $stage->status = 'complete';
                        $stage->completed_at = now();
                        $stage->completed_by_id = $fabUserId;
                        $stage->save();

                        FdStageLog::create([
                            'stage_id' => $stage->id,
                            'user_id' => $resolution['log_user_id'] ?? null,
                            'message' => "Completed via shop-floor bulk step complete by {$actor}",
                        ]);
                        $updated++;
                    }

                    // Close the line if this bulk-completed step was the last one
                    // outstanding on the elevation.
                    $elevation->load('stages');
                    $allTerminal = $elevation->stages->every(
                        fn ($s) => in_array($s->status, ['complete', 'not_required'], true)
                    );
                    if ($allTerminal && ! $elevation->date_completed) {
                        $elevation->date_completed = now()->toDateString();
                        $elevation->completed_by_id = $fabUserId;
                        $elevation->save();
                        $elevationsClosed++;
                    }
                }
            });

            if ($elevationsClosed > 0) {
                $wo->recalcDueDateFromElevations();
                FdWorkOrder::resequencePriorities();
            }

            return response()->json([
                'updated' => $updated,
                'elevations_completed' => $elevationsClosed,
            ]);
        } catch (StageGatedException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('ShopFloorController@bulkCompleteWoStage failed', ['id' => $id, 'message' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to complete stages'], 500);
        }
    }

    /**
     * A single operator's ranked, actionable work queue.
     *
     * Includes stages that are pending/in_progress on an open elevation of a
     * live WO, and are either assigned to this operator or unassigned on a WO
     * this operator is on the crew for. Gated stages are excluded — the queue
     * only ever shows work that can be started right now. Ordered:
     * in_progress first, then WO priority, then elevation date_requested, then
     * stage sort_order.
     */
    public function myQueue(Request $request)
    {
        $request->validate(['fab_user_id' => 'required|integer|exists:fd_users,id']);
        $uid = (int) $request->fab_user_id;

        $stages = FdWoStage::query()
            ->actionable()
            ->where(function ($q) use ($uid) {
                // Directly assigned (primary column or the multi-assignee pivot) …
                $q->where('fd_wo_stages.assigned_to_id', $uid)
                    ->orWhereExists(function ($sub) use ($uid) {
                        $sub->selectRaw('1')->from('fd_wo_stage_assignees as sa')
                            ->whereColumn('sa.stage_id', 'fd_wo_stages.id')
                            ->where('sa.user_id', $uid);
                    })
                  // … or unassigned on a WO this operator is on the crew for.
                    ->orWhere(function ($q2) use ($uid) {
                        $q2->whereNull('fd_wo_stages.assigned_to_id')
                            ->whereNotExists(function ($sub) {
                                $sub->selectRaw('1')->from('fd_wo_stage_assignees as sa2')
                                    ->whereColumn('sa2.stage_id', 'fd_wo_stages.id');
                            })
                            ->whereExists(function ($sub) use ($uid) {
                                $sub->selectRaw('1')->from('fd_wo_assignments as a')
                                    ->whereColumn('a.work_order_id', 'w.id')
                                    ->where('a.user_id', $uid);
                            });
                    });
            })
            ->with(['elevation.stages', 'elevation.workOrder.businessJob', 'elevation.doorFrameConfiguration', 'assignedTo', 'assignees'])
            ->orderByRaw("CASE WHEN fd_wo_stages.status = 'in_progress' THEN 0 ELSE 1 END")
            ->orderByRaw('w.priority IS NULL, w.priority ASC')
            ->orderByRaw('e.date_requested IS NULL, e.date_requested ASC')
            ->orderBy('fd_wo_stages.sort_order')
            ->orderBy('fd_wo_stages.id')
            ->get();

        $gate = app(StageGateService::class);

        $queue = $stages
            ->reject(fn ($s) => $gate->blockingStageForLoaded($s, $s->elevation->stages) !== null
                || $gate->blockingConfigurationReasonFor($s) !== null)
            ->map(function ($s) {
                $wo = $s->elevation->workOrder;
                $job = $wo?->businessJob;

                return [
                    'stage_id' => $s->id,
                    'name' => $s->name,
                    'status' => $s->status,
                    'sort_order' => $s->sort_order,
                    'phase' => $s->phase,
                    'blocks_next' => (bool) $s->blocks_next,
                    'assigned_to_id' => $s->assigned_to_id,
                    'assigned_name' => $s->assignedTo?->name,
                    'assignee_ids' => $s->assignees->pluck('id')->values(),
                    'assignee_names' => $s->assignees->pluck('name')->values(),
                    'elevation_id' => $s->elevation_id,
                    'elevation_tag' => $s->elevation->elevation_tag,
                    'date_requested' => $s->elevation->date_requested?->format('Y-m-d'),
                    'work_order_id' => $wo?->id,
                    'release_label' => $job ? "{$job->job_number}-{$wo->release_token}" : "{$wo?->release_token}",
                    'job_name' => $job?->job_name,
                    'priority' => $wo?->priority,
                    'due_date' => $wo?->due_date?->format('Y-m-d'),
                ];
            })
            ->values();

        return response()->json(['queue' => $queue]);
    }

    public function assignStage(Request $request, int $id)
    {
        try {
            $stage = FdWoStage::findOrFail($id);
            $stage->syncAssignees($request->user_id ? [(int) $request->user_id] : []);

            return response()->json(['assigned' => $id]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to assign stage'], 500);
        }
    }

    private function formatWo(FdWorkOrder $wo): array
    {
        $job = $wo->businessJob;
        $users = $wo->relationLoaded('assignedUsers') ? $wo->assignedUsers : collect();

        return [
            'id' => $wo->id,
            'release_label' => $job ? "{$job->job_number}-{$wo->release_token}" : "{$wo->release_token}",
            'job_name' => $job?->job_name ?? '—',
            'job_number' => $job?->job_number ?? '—',
            'date_issued' => $wo->date_issued?->format('Y-m-d'),
            'due_date' => $wo->due_date?->format('Y-m-d'),
            'priority' => $wo->priority,
            'priority_locked' => (bool) $wo->priority_locked,
            'assigned_users' => $users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'initials' => $u->initials,
            ])->values(),
            'elevations' => $wo->elevations->map(fn ($e) => [
                'id' => $e->id,
                'elevation_tag' => $e->elevation_tag,
                'scope' => $e->scope,
                'date_requested' => $e->date_requested?->format('Y-m-d'),
                'date_completed' => $e->date_completed?->format('Y-m-d'),
                'completed_by_id' => $e->completed_by_id,
                'completed_by_name' => $e->completedBy?->name,
                'elevation_type' => $e->elevationType ? [
                    'id' => $e->elevationType->id,
                    'name' => $e->elevationType->name,
                    'color' => $e->elevationType->color,
                ] : null,
                'stages' => $e->stages->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'status' => $s->status,
                    'sort_order' => $s->sort_order,
                    'phase' => $s->phase,
                    'blocks_next' => (bool) $s->blocks_next,
                    'assigned_to_id' => $s->assigned_to_id,
                    'assigned_name' => $s->assignedTo?->name,
                    'assigned_initials' => $s->assignedTo?->initials,
                    'assignee_ids' => $s->relationLoaded('assignees') ? $s->assignees->pluck('id')->values() : [],
                    'assignees' => $s->relationLoaded('assignees') ? $s->assignees->map(fn ($u) => [
                        'id' => $u->id, 'name' => $u->name, 'initials' => $u->initials,
                    ])->values() : [],
                    'completed_by_id' => $s->completed_by_id,
                    'completed_by_name' => $s->completedBy?->name,
                    'started_at' => $s->started_at?->toIso8601String(),
                    'completed_at' => $s->completed_at?->toIso8601String(),
                ])->values(),
            ])->values(),
        ];
    }
}
