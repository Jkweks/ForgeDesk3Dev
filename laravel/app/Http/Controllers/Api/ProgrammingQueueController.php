<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdUser;
use App\Models\FdWoStage;
use App\Services\StageGateService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The CNC programmers' worklist: one row per elevation (line item) that has a
 * programming step ("Program", "Programmed", "CNC Programming"... — any step
 * whose name contains "program"). Ranked like the Work Queue: in-progress
 * first, then work-order priority, then the line's requested date. A programmer can drag lines into
 * their own order (programming_rank), which takes precedence until reset; it never touches the work
 * orders' own priority, which the Work Queue owns.
 */
class ProgrammingQueueController extends Controller
{
    /** Recently finished programming stays visible this many days when asked for. */
    private const DONE_WINDOW_DAYS = 14;

    public function index(Request $request)
    {
        $request->validate([
            'job_id' => 'sometimes|integer',
            'work_order_id' => 'sometimes|integer',
            'include_done' => 'sometimes|boolean',
        ]);

        $includeDone = $request->boolean('include_done');

        $query = FdWoStage::query()
            ->select('fd_wo_stages.*')
            ->join('fd_wo_elevations as e', 'e.id', '=', 'fd_wo_stages.elevation_id')
            ->join('fd_work_orders as w', 'w.id', '=', 'e.work_order_id')
            ->whereRaw("LOWER(fd_wo_stages.name) LIKE '%program%'")
            ->where('w.archived', false)
            ->where(function ($q) use ($includeDone) {
                $q->where(fn ($open) => $open
                    ->whereIn('fd_wo_stages.status', ['pending', 'in_progress'])
                    ->whereNull('e.date_completed'));
                if ($includeDone) {
                    $q->orWhere(fn ($done) => $done
                        ->where('fd_wo_stages.status', 'complete')
                        ->where('fd_wo_stages.completed_at', '>=', now()->subDays(self::DONE_WINDOW_DAYS)));
                }
            })
            ->with(['elevation.stages', 'elevation.workOrder.businessJob', 'elevation.elevationType', 'assignees', 'assignedTo']);

        if ($request->filled('work_order_id')) {
            $query->where('w.id', (int) $request->work_order_id);
        } elseif ($request->filled('job_id')) {
            $query->where('w.business_job_id', (int) $request->job_id);
        }

        $stages = $query
            // Finished lines stay at the bottom; among the rest, hand-ordered lines come first in their dragged order.
            ->orderByRaw("CASE WHEN fd_wo_stages.status = 'complete' THEN 1 ELSE 0 END")
            ->orderByRaw('fd_wo_stages.programming_rank IS NULL, fd_wo_stages.programming_rank ASC')
            ->orderByRaw("CASE fd_wo_stages.status WHEN 'in_progress' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->orderByRaw('w.priority IS NULL, w.priority ASC')
            ->orderByRaw('e.date_requested IS NULL, e.date_requested ASC')
            ->orderBy('fd_wo_stages.id')
            ->get();

        $gate = app(StageGateService::class);

        // Total labor estimate per work order (minutes) — how long fab has once it starts
        $workOrders = $stages->map(fn ($s) => $s->elevation->workOrder)->filter()->unique('id');
        $workOrders->load(['elevations.stages', 'elevations.templateSet']);
        $estimates = $workOrders->mapWithKeys(fn ($wo) => [$wo->id => $wo->estimateMinutes()['effective']]);

        $rows = $stages->map(function (FdWoStage $s) use ($gate, $estimates) {
            $e = $s->elevation;
            $wo = $e->workOrder;
            // Legacy rows may have only the primary-assignee column and no pivot row yet
            $assignees = $s->assignees->isEmpty() && $s->assignedTo ? collect([$s->assignedTo]) : $s->assignees;
            $job = $wo?->businessJob;
            $blocker = $s->isTerminal() ? null : $gate->blockingStageForLoaded($s, $e->stages);
            $configReason = $s->isTerminal() ? null : $gate->blockingConfigurationReasonFor($s);

            // Programming has to be finished the working day before fab starts (per the Work Queue's planned start)
            $plannedStart = $wo?->planned_start_date;
            $programmingMinutes = ($s->minutes_per_joint !== null && $e->joint_qty !== null)
                ? (int) round($e->joint_qty * (float) $s->minutes_per_joint)
                : null;

            return [
                'stage_id' => $s->id,
                'stage_name' => $s->name,
                'status' => $s->status,
                'programming_rank' => $s->programming_rank,
                'started_at' => $s->started_at?->toIso8601String(),
                'completed_at' => $s->completed_at?->toIso8601String(),
                'gated' => $blocker !== null || $configReason !== null,
                'blocking_stage_name' => $blocker?->name ?? $configReason,
                'assignee_ids' => $assignees->pluck('id')->values(),
                'assignee_names' => $assignees->pluck('name')->values(),
                'elevation_id' => $e->id,
                'elevation_tag' => $e->elevation_tag,
                'elevation_type' => $e->elevationType?->name,
                'quantity' => $e->quantity,
                'notes' => $e->notes,
                'date_requested' => $e->date_requested?->format('Y-m-d'),
                // The line's other steps, in order, so the programmer sees what follows (CNC, assembly...)
                'steps' => $e->stages->sortBy('sort_order')->values()->map(fn ($x) => [
                    'name' => $x->name,
                    'status' => $x->status,
                    'is_this' => $x->id === $s->id,
                ]),
                'work_order_id' => $wo?->id,
                'business_job_id' => $wo?->business_job_id,
                'job_number' => $job?->job_number,
                'job_name' => $job?->job_name,
                'release_label' => $job ? "{$job->job_number}-{$wo->release_token}" : (string) $wo?->release_token,
                'priority' => $wo?->priority,
                'due_date' => $wo?->due_date?->format('Y-m-d'),
                'planned_start_date' => $plannedStart?->format('Y-m-d'),
                'planned_start_quarter' => $wo?->planned_start_quarter ?? 0,
                'programming_date' => $plannedStart ? self::workingDayBefore($plannedStart)->format('Y-m-d') : null,
                'programming_minutes' => $programmingMinutes,
                'fab_estimated_minutes' => $wo ? $estimates->get($wo->id) : null,
            ];
        })->values();

        return response()->json([
            'rows' => $rows,
            'fab_users' => FdUser::where('active', true)->orderBy('name')->get(['id', 'name', 'initials']),
        ]);
    }

    /**
     * Saves a drag-and-drop order. Body: { ordered_ids: [stage ids, top to bottom] }. Only programming
     * steps are touched; ids that aren't (or are unknown) are ignored.
     */
    public function reorder(Request $request)
    {
        $ids = $request->validate([
            'ordered_ids' => 'required|array|min:1|max:2000',
            'ordered_ids.*' => 'integer|distinct',
        ])['ordered_ids'];

        $valid = FdWoStage::whereIn('id', $ids)
            ->whereRaw("LOWER(name) LIKE '%program%'")
            ->pluck('id')->flip();

        DB::transaction(function () use ($ids, $valid) {
            $rank = 0;
            foreach ($ids as $id) {
                if ($valid->has($id)) {
                    FdWoStage::whereKey($id)->update(['programming_rank' => ++$rank]);
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    /** Drops the hand ordering so the queue goes back to priority order. */
    public function resetOrder()
    {
        FdWoStage::whereNotNull('programming_rank')->update(['programming_rank' => null]);

        return response()->json(['ok' => true]);
    }

    /** The Monday-to-Friday day before $date (a Monday start means programming is due the Friday before). */
    public static function workingDayBefore(Carbon $date): Carbon
    {
        $day = $date->copy()->startOfDay()->subDay();
        while ($day->isWeekend()) {
            $day->subDay();
        }

        return $day;
    }
}
