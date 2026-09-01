<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdStageLog;
use App\Models\FdUser;
use App\Models\FdWoElevation;
use App\Models\FdWoStage;
use App\Models\FdWorkOrder;
use App\Services\StageGateService;
use App\Services\StageOverrideResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class WorkOrderStageController extends Controller
{
    public function __construct(
        private StageGateService $gate,
        private StageOverrideResolver $overrides,
    ) {}

    /**
     * Assign every non-terminal stage with a given name (across all elevations of
     * a work order, or a whole job) to one operator in a single call.
     *
     * Body: { work_order_id | job_id, assignments: [{ stage_name, assigned_to_id }] }
     * `assigned_to_id` null clears the assignee. Returns per-name change counts.
     */
    public function bulkAssign(Request $request)
    {
        $data = $request->validate([
            'work_order_id'                  => 'required_without:job_id|integer|exists:fd_work_orders,id',
            'job_id'                         => 'required_without:work_order_id|integer|exists:business_jobs,id',
            'assignments'                    => 'required|array|min:1',
            'assignments.*.stage_name'       => 'required|string|max:255',
            // Either a single id (back-compat) or a set of ids for co-assignment.
            'assignments.*.assigned_to_id'   => 'nullable|integer|exists:fd_users,id',
            'assignments.*.assigned_to_ids'  => 'sometimes|array',
            'assignments.*.assigned_to_ids.*' => 'integer|exists:fd_users,id',
        ]);

        $woId = $data['work_order_id'] ?? null;

        $elevationIds = FdWoElevation::query()
            ->when($woId, fn ($q) => $q->where('work_order_id', $woId))
            ->when(! $woId, fn ($q) => $q->whereIn(
                'work_order_id',
                FdWorkOrder::where('business_job_id', $data['job_id'])->pluck('id')
            ))
            ->pluck('id');

        $actor = trim(($request->user()?->name ?? 'Office user')) . ' (bulk assign)';

        $byStage = [];
        $total = 0;

        DB::transaction(function () use ($data, $elevationIds, $woId, $actor, &$byStage, &$total) {
            foreach ($data['assignments'] as $a) {
                $ids = array_key_exists('assigned_to_ids', $a)
                    ? $a['assigned_to_ids']
                    : (isset($a['assigned_to_id']) ? [$a['assigned_to_id']] : []);
                $newIds = collect($ids)->map(fn ($v) => (int) $v)->filter()->unique()->sort()->values();

                $stages = FdWoStage::query()
                    ->with('assignees')
                    ->where(function ($q) use ($elevationIds, $woId) {
                        $q->whereIn('elevation_id', $elevationIds);
                        if ($woId) {
                            $q->orWhere('work_order_id', $woId);
                        }
                    })
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($a['stage_name'])])
                    ->whereNotIn('status', FdWoStage::TERMINAL)
                    ->get();

                $changed = 0;
                foreach ($stages as $s) {
                    // Pivot is the source of truth; fall back to the legacy
                    // single-assignee column for rows not yet migrated.
                    $currentIds = $s->assignees->pluck('id');
                    if ($currentIds->isEmpty() && $s->assigned_to_id) {
                        $currentIds = collect([$s->assigned_to_id]);
                    }
                    $currentIds = $currentIds->map(fn ($v) => (int) $v)->sort()->values();

                    if ($currentIds->all() === $newIds->all()) {
                        continue;
                    }

                    $prev = $currentIds->isNotEmpty()
                        ? FdUser::whereIn('id', $currentIds)->orderBy('name')->pluck('name')->join(', ')
                        : 'Unassigned';
                    $s->syncAssignees($newIds->all());
                    $new = $s->assignees()->orderBy('name')->pluck('name')->join(', ') ?: 'Unassigned';

                    FdStageLog::create([
                        'stage_id' => $s->id,
                        'user_id'  => null,
                        'message'  => "Reassigned from {$prev} to {$new} via bulk assign by {$actor}",
                    ]);
                    $changed++;
                }

                $byStage[$a['stage_name']] = $changed;
                $total += $changed;
            }
        });

        return response()->json(['updated' => $total, 'by_stage' => $byStage]);
    }

    public function index(Request $request)
    {
        $request->validate(['wo_id' => 'required|integer']);

        try {
            $stages = FdWoStage::with(['assignedTo', 'assignees', 'completedBy', 'log'])
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
            'work_order_id'      => 'required|integer|exists:fd_work_orders,id',
            'name'               => 'required|string|max:255',
            'assigned_to_id'     => 'sometimes|nullable|integer|exists:fd_users,id',
            'assigned_to_ids'    => 'sometimes|array',
            'assigned_to_ids.*'  => 'integer|exists:fd_users,id',
        ]);

        try {
            $maxOrder = FdWoStage::where('work_order_id', $request->work_order_id)->max('sort_order') ?? 0;

            $stage = FdWoStage::create([
                'work_order_id' => $request->work_order_id,
                'name'          => $request->name,
                'description'   => $request->description,
                'sort_order'    => $maxOrder + 1,
                'blocks_next'   => $request->boolean('blocks_next', true),
                'status'        => 'pending',
                'assigned_to_id' => $request->assigned_to_id,
                'notes'         => $request->notes,
            ]);

            if ($request->has('assigned_to_ids')) {
                $stage->syncAssignees((array) $request->input('assigned_to_ids', []));
            } elseif ($request->filled('assigned_to_id')) {
                $stage->syncAssignees([(int) $request->assigned_to_id]);
            }

            return response()->json(['id' => $stage->id], 201);
        } catch (\Exception $e) {
            Log::error('WorkOrderStageController@store failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create stage'], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $stage = FdWoStage::findOrFail($id);

        $request->validate([
            'status'            => ['sometimes', Rule::in(FdWoStage::STATUSES)],
            'blocks_next'       => 'sometimes|boolean',
            'override'          => 'sometimes|boolean',
            'assigned_to_id'    => 'sometimes|nullable|integer|exists:fd_users,id',
            'assigned_to_ids'   => 'sometimes|array',
            'assigned_to_ids.*' => 'integer|exists:fd_users,id',
        ]);

        // Gate + StageGatedException render OUTSIDE the try/catch. Runs against
        // the pre-fill status so an in_progress activation is still checked.
        if ($request->has('status')) {
            $resolution = $this->overrides->resolve($request);
            $this->gate->guardStageTransition(
                $stage,
                $request->status,
                $resolution['allowed'],
                $this->overrides->stageLogger($stage, $resolution),
            );
        }

        try {
            $allowed = ['name', 'description', 'status', 'sort_order', 'blocks_next', 'assigned_to_id', 'completed_by_id', 'notes', 'started_at', 'completed_at'];
            $stage->fill($request->only($allowed));

            // Auto-timestamp on status transitions
            if ($request->has('status')) {
                if ($request->status === 'in_progress' && is_null($stage->started_at)) {
                    $stage->started_at = now();
                }
                if ($request->status === 'complete') {
                    if (is_null($stage->completed_at)) $stage->completed_at = now();
                    if (!$request->has('completed_by_id')) {} // leave existing if not sent
                }
                if (in_array($request->status, ['pending', 'not_required', 'on_hold'])) {
                    $stage->started_at    = null;
                    $stage->completed_at  = null;
                    $stage->completed_by_id = null;
                }
            }

            $stage->save();

            // Keep the assignees pivot in sync. `assigned_to_ids` (a set) wins;
            // otherwise mirror a single `assigned_to_id` write into the pivot.
            if ($request->has('assigned_to_ids')) {
                $stage->syncAssignees((array) $request->input('assigned_to_ids', []));
            } elseif ($request->has('assigned_to_id')) {
                $stage->syncAssignees($stage->assigned_to_id ? [(int) $stage->assigned_to_id] : []);
            }

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
            'id'                => $s->id,
            'work_order_id'     => $s->work_order_id,
            'template_id'       => $s->template_id,
            'name'              => $s->name,
            'description'       => $s->description,
            'sort_order'        => $s->sort_order,
            'blocks_next'       => (bool) $s->blocks_next,
            'status'            => $s->status,
            'assigned_to_id'    => $s->assigned_to_id,
            'assigned_name'     => $s->assignedTo?->name,
            'assignee_ids'      => $s->relationLoaded('assignees') ? $s->assignees->pluck('id')->values() : [],
            'assignee_names'    => $s->relationLoaded('assignees') ? $s->assignees->pluck('name')->values() : [],
            'completed_by_id'   => $s->completed_by_id,
            'completed_by_name' => $s->completedBy?->name,
            'started_at'        => $s->started_at?->toIso8601String(),
            'completed_at'      => $s->completed_at?->toIso8601String(),
            'notes'             => $s->notes,
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
