<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\StageGatedException;
use App\Http\Controllers\Controller;
use App\Models\FdStageTemplate;
use App\Models\FdStageTemplateSet;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Models\FdWoStage;
use App\Models\DoorFrameConfiguration;
use App\Services\Configurator\ElevationConfigurationMatcher;
use App\Services\StageGateService;
use App\Services\StageOverrideResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ElevationController extends Controller
{
    public function __construct(
        private StageGateService $gate,
        private StageOverrideResolver $overrides,
        private ElevationConfigurationMatcher $matcher,
    ) {}

    public function index(int $workOrderId)
    {
        $wo = FdWorkOrder::findOrFail($workOrderId);
        $elevations = $wo->elevations()
            ->with(['elevationType', 'doorFrameConfiguration', 'completedBy', 'templateSet', 'stages.assignedTo'])
            ->get()
            ->map(fn ($e) => $this->formatElevation($e));

        return response()->json(['elevations' => $elevations]);
    }

    /**
     * Draft configurator openings for this work order's job that aren't tied
     * to any work order yet — candidates the Door Schedule can pull in as-is
     * instead of re-creating the same opening as bare elevation rows.
     */
    public function availableConfigurations(int $workOrderId)
    {
        $wo = FdWorkOrder::findOrFail($workOrderId);

        $configurations = DoorFrameConfiguration::where('business_job_id', $wo->business_job_id)
            ->whereNull('work_order_id')
            ->with('doors')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'job_scope' => $c->job_scope,
                'status' => $c->status,
                'status_label' => $c->status_label,
                'quantity' => $c->quantity,
                'door_tags' => $c->doors->pluck('door_tag')->values(),
            ]);

        return response()->json(['configurations' => $configurations]);
    }

    /**
     * Pull an existing, unlinked configurator opening into this work order's
     * Door Schedule — creates its Door/Frame elevation rows the same way
     * ElevationConfigurationMatcher::createElevationRow() always does, then
     * stamps the configuration as belonging to this work order.
     */
    public function attachConfiguration(int $workOrderId, int $configId)
    {
        $wo = FdWorkOrder::findOrFail($workOrderId);
        $config = DoorFrameConfiguration::with('doors')->findOrFail($configId);

        if ($config->work_order_id) {
            return response()->json(['error' => 'This configuration is already tied to a work order.'], 422);
        }
        if ($config->business_job_id !== $wo->business_job_id) {
            return response()->json(['error' => 'This configuration belongs to a different job.'], 422);
        }

        try {
            $created = $this->matcher->attachConfigurationToWorkOrder($config, $wo);

            $wo->recalcDueDateFromElevations();
            FdWorkOrder::resequencePriorities();

            $created->each->load(['elevationType', 'doorFrameConfiguration', 'completedBy', 'templateSet', 'stages.assignedTo']);

            return response()->json([
                'elevations' => $created->map(fn ($e) => $this->formatElevation($e)),
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('ElevationController@attachConfiguration failed', ['config_id' => $configId, 'message' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to attach configuration'], 500);
        }
    }

    public function store(Request $request, int $workOrderId)
    {
        $request->validate([
            'elevation_tag' => 'required|string|max:100',
            'elevation_type_id' => 'nullable|integer|exists:fd_elevation_types,id',
            'template_set_id' => 'nullable|integer|exists:fd_stage_template_sets,id',
            'joint_qty' => 'sometimes|nullable|integer|min:0',
        ]);

        $workOrder = FdWorkOrder::findOrFail($workOrderId);

        try {
            // Shared with the configurator integration (attaching/syncing an
            // opening's elevations) so both paths create production-ready
            // rows the same way — template-set resolution, joint_qty
            // defaulting, and stage seeding all live in one place.
            $elevation = $this->matcher->createElevationRow($workOrder, [
                'elevation_tag' => $request->elevation_tag,
                'elevation_type_id' => $request->elevation_type_id,
                'template_set_id' => $request->template_set_id,
                'quantity' => $request->quantity ?? 1,
                'joint_qty' => $request->filled('joint_qty') ? (int) $request->joint_qty : null,
                'date_requested' => $request->date_requested,
                'notes' => $request->notes,
                'scope' => $request->scope ?? 'assemble',
            ]);

            // Elevation dates drive the work order's due date + ranking.
            $workOrder->recalcDueDateFromElevations();
            FdWorkOrder::resequencePriorities();

            $elevation->load(['elevationType', 'doorFrameConfiguration', 'completedBy', 'templateSet', 'stages.assignedTo']);

            return response()->json($this->formatElevation($elevation), 201);
        } catch (\Exception $e) {
            Log::error('ElevationController@store failed', ['message' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to create elevation'], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $elevation = FdWoElevation::findOrFail($id);

            $request->validate([
                'template_set_id' => 'sometimes|nullable|integer|exists:fd_stage_template_sets,id',
            ]);

            $oldSetId = $elevation->template_set_id;

            $elevation->fill($request->only([
                'elevation_type_id', 'template_set_id', 'elevation_tag', 'quantity', 'joint_qty',
                'date_requested', 'date_completed', 'completed_by_id', 'notes', 'scope',
            ]));

            // Empty string from the form clears the joint quantity.
            if ($request->has('joint_qty') && ! $request->filled('joint_qty')) {
                $elevation->joint_qty = null;
            }

            $newSetId = $elevation->template_set_id;
            $resyncSummary = null;

            DB::transaction(function () use ($elevation, $request, $oldSetId, $newSetId, &$resyncSummary) {
                // Bumping (or switching) the tier reconciles the stage list.
                if ($request->has('template_set_id') && $newSetId && (int) $newSetId !== (int) $oldSetId) {
                    // With nothing started/held/finished, drop every stage and
                    // rebuild cleanly from the new tier. Otherwise fall back to
                    // the non-destructive merge so progress is never lost.
                    $hasProgress = $elevation->stages()
                        ->whereIn('status', ['in_progress', 'on_hold', 'complete'])
                        ->exists();

                    $resyncSummary = $hasProgress
                        ? $this->resyncStagesToSet($elevation, (int) $newSetId)
                        : $this->rebuildStagesFromSet($elevation, (int) $newSetId);
                }
                $elevation->save();
            });

            // date_requested may have moved — keep the WO due date + ranking in step.
            if ($wo = $elevation->workOrder()->first()) {
                $wo->recalcDueDateFromElevations();
                FdWorkOrder::resequencePriorities();
            }

            $elevation->load(['elevationType', 'doorFrameConfiguration', 'completedBy', 'templateSet', 'stages.assignedTo', 'stages.completedBy']);
            $payload = $this->formatElevation($elevation);
            if ($resyncSummary !== null) {
                $payload['resync_summary'] = $resyncSummary;
            }

            return response()->json($payload);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('ElevationController@update failed', ['id' => $id, 'message' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to update elevation'], 500);
        }
    }

    /**
     * Reconcile an elevation's stages against a (new) tier's templates.
     *
     *  - match existing stages by template_id, then by case-insensitive name
     *  - matched stages keep their status/assignee/timestamps; only sort_order,
     *    blocks_next and minutes_per_joint are re-pulled from the template
     *  - templates with no match are added as fresh `pending` stages
     *  - existing stages absent from the new tier: retired to `not_required` if
     *    untouched, left alone if they already have progress
     *
     * @return array{added: string[], carried: string[], retired: string[], kept_with_progress: string[]}
     */
    private function resyncStagesToSet(FdWoElevation $elevation, int $newSetId): array
    {
        $templates = FdStageTemplate::where('template_set_id', $newSetId)->orderBy('sort_order')->get();
        $existing = $elevation->stages()->get();

        $added = $carried = $retired = $keptWithProgress = [];
        $keepIds = [];

        foreach ($templates as $tpl) {
            $match = $existing->first(fn ($s) => $s->template_id === $tpl->id && ! in_array($s->id, $keepIds, true))
                ?? $existing->first(fn ($s) => mb_strtolower($s->name) === mb_strtolower($tpl->name) && ! in_array($s->id, $keepIds, true));

            if ($match) {
                $match->sort_order = $tpl->sort_order;
                $match->phase = $tpl->phase;
                $match->blocks_next = $tpl->blocks_next ?? true;
                $match->minutes_per_joint = $tpl->minutes_per_joint;
                $match->template_id = $tpl->id;
                $match->save();
                $keepIds[] = $match->id;
                $carried[] = $match->name;
            } else {
                $stage = FdWoStage::create([
                    'elevation_id' => $elevation->id,
                    'work_order_id' => null,
                    'template_id' => $tpl->id,
                    'name' => $tpl->name,
                    'description' => $tpl->description,
                    'sort_order' => $tpl->sort_order,
                    'phase' => $tpl->phase,
                    'blocks_next' => $tpl->blocks_next ?? true,
                    'minutes_per_joint' => $tpl->minutes_per_joint,
                    'status' => 'pending',
                    'assigned_to_id' => $tpl->default_user_id,
                ]);
                $keepIds[] = $stage->id;
                $added[] = $stage->name;
            }
        }

        foreach ($existing as $orphan) {
            if (in_array($orphan->id, $keepIds, true)) {
                continue;
            }
            $hasProgress = $orphan->status !== 'pending' || $orphan->started_at || $orphan->completed_at;
            if ($hasProgress) {
                $keptWithProgress[] = $orphan->name;
            } else {
                $orphan->status = 'not_required';
                $orphan->save();
                $retired[] = $orphan->name;
            }
        }

        return [
            'added' => $added,
            'carried' => $carried,
            'retired' => $retired,
            'kept_with_progress' => $keptWithProgress,
        ];
    }

    /**
     * Tear down every stage on an elevation and reseed from a tier's templates.
     * Only safe when nothing has started — the caller checks that.
     *
     * Assignment: a template with a default operator hands the new stage to that
     * operator outright; templates with no default inherit the work order's
     * assigned crew (co-assigned).
     *
     * @return array{added: string[], carried: string[], retired: string[], kept_with_progress: string[]}
     */
    private function rebuildStagesFromSet(FdWoElevation $elevation, int $newSetId): array
    {
        $templates = FdStageTemplate::where('template_set_id', $newSetId)->orderBy('sort_order')->get();

        $existing = $elevation->stages()->get();
        $retired = $existing->pluck('name')->values()->all();
        foreach ($existing as $old) {
            $old->assignees()->detach();
            $old->delete();
        }

        $woUserIds = $elevation->workOrder
            ? $elevation->workOrder->assignedUsers()->pluck('fd_users.id')->map(fn ($v) => (int) $v)->all()
            : [];

        $added = [];
        foreach ($templates as $tpl) {
            $stage = FdWoStage::create([
                'elevation_id' => $elevation->id,
                'work_order_id' => null,
                'template_id' => $tpl->id,
                'name' => $tpl->name,
                'description' => $tpl->description,
                'sort_order' => $tpl->sort_order,
                'phase' => $tpl->phase,
                'blocks_next' => $tpl->blocks_next ?? true,
                'minutes_per_joint' => $tpl->minutes_per_joint,
                'status' => 'pending',
                'assigned_to_id' => $tpl->default_user_id,
            ]);

            $ids = $tpl->default_user_id ? [(int) $tpl->default_user_id] : $woUserIds;
            if ($ids) {
                $stage->syncAssignees($ids);
            }
            $added[] = $stage->name;
        }

        return [
            'added' => $added,
            'carried' => [],
            'retired' => $retired,
            'kept_with_progress' => [],
        ];
    }

    /**
     * Complete every outstanding stage on one elevation (in sort order so gates
     * clear as we go), then stamp the line itself as complete once all its
     * stages are terminal. Stages on hold / blocked / not-required are left as
     * they are; a lingering gate can be pushed past with `override`.
     *
     * Body: { fab_user_id?, override? }
     * `fab_user_id` is credited on both the stages and the elevation, and is
     * honoured only for manager / admin app users.
     */
    public function completeAllStages(Request $request, int $id)
    {
        $data = $request->validate([
            'fab_user_id' => 'nullable|integer|exists:fd_users,id',
            'override' => 'sometimes|boolean',
        ]);

        $elevation = FdWoElevation::with('stages')->findOrFail($id);
        $isManager = in_array($request->user()?->role, ['admin', 'manager'], true);
        $fabUserId = $isManager ? ($data['fab_user_id'] ?? null) : null;
        $resolution = $this->overrides->resolve($request);

        try {
            $updated = 0;

            DB::transaction(function () use ($elevation, $fabUserId, $resolution, &$updated) {
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

                // Close the line if every stage is now terminal.
                $elevation->load('stages');
                $allTerminal = $elevation->stages->every(
                    fn ($s) => in_array($s->status, ['complete', 'not_required'], true)
                );
                if ($allTerminal && ! $elevation->date_completed) {
                    $elevation->date_completed = now()->toDateString();
                    $elevation->completed_by_id = $fabUserId;
                    $elevation->save();
                }
            });

            if ($wo = $elevation->workOrder()->first()) {
                $wo->recalcDueDateFromElevations();
                FdWorkOrder::resequencePriorities();
            }

            $elevation->load(['elevationType', 'doorFrameConfiguration', 'completedBy', 'templateSet', 'stages.assignedTo', 'stages.completedBy']);

            return response()->json($this->formatElevation($elevation));
        } catch (StageGatedException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('ElevationController@completeAllStages failed', ['id' => $id, 'message' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to complete stages'], 500);
        }
    }

    public function destroy(int $id)
    {
        $elevation = FdWoElevation::findOrFail($id);
        $workOrderId = $elevation->work_order_id;
        $elevation->delete();

        if ($workOrderId && ($wo = FdWorkOrder::find($workOrderId))) {
            $wo->recalcDueDateFromElevations();
            FdWorkOrder::resequencePriorities();
        }

        return response()->json(['deleted' => $id]);
    }

    private function formatElevation(FdWoElevation $e): array
    {
        $stages = $e->relationLoaded('stages') ? $e->stages : $e->stages()->with(['assignedTo', 'completedBy'])->get();
        $stageCount = $stages->count();
        $stagesDone = $stages->whereIn('status', ['complete', 'not_required'])->count();
        $stagesActive = $stages->where('status', 'in_progress')->count();
        $stagesBlocked = $stages->where('status', 'blocked')->count();

        $estimate = $e->estimateMinutes();

        return [
            'id' => $e->id,
            'work_order_id' => $e->work_order_id,
            'elevation_type_id' => $e->elevation_type_id,
            'template_set_id' => $e->template_set_id,
            'elevation_type' => $e->elevationType ? [
                'id' => $e->elevationType->id,
                'name' => $e->elevationType->name,
                'color' => $e->elevationType->color,
            ] : null,
            'elevation_tag' => $e->elevation_tag,
            'door_frame_configuration_id' => $e->door_frame_configuration_id,
            'door_frame_configuration' => $e->doorFrameConfiguration ? [
                'id' => $e->doorFrameConfiguration->id,
                'status' => $e->doorFrameConfiguration->status,
                'status_label' => $e->doorFrameConfiguration->status_label,
            ] : null,
            'quantity' => $e->quantity,
            'joint_qty' => $e->joint_qty,
            'minutes_per_joint' => round($estimate['rate'], 2),
            'estimated_minutes' => $estimate['effective'],
            'date_requested' => $e->date_requested?->format('Y-m-d'),
            'date_completed' => $e->date_completed?->format('Y-m-d'),
            'completed_by_id' => $e->completed_by_id,
            'completed_by_name' => $e->completedBy?->name,
            'notes' => $e->notes,
            'scope' => $e->scope ?? 'assemble',
            'stage_count' => $stageCount,
            'stages_done' => $stagesDone,
            'stages_active' => $stagesActive,
            'stages_blocked' => $stagesBlocked,
            'stages' => $stages->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'status' => $s->status,
                'sort_order' => $s->sort_order,
                'phase' => $s->phase,
                'blocks_next' => (bool) $s->blocks_next,
                'minutes_per_joint' => $s->minutes_per_joint !== null ? (float) $s->minutes_per_joint : null,
                'assigned_name' => $s->assignedTo?->name,
                'completed_by_id' => $s->completed_by_id,
                'completed_by_name' => $s->completedBy?->name,
                'started_at' => $s->started_at?->toIso8601String(),
                'completed_at' => $s->completed_at?->toIso8601String(),
            ])->values(),
            'created_at' => $e->created_at->toIso8601String(),
            'updated_at' => $e->updated_at->toIso8601String(),
        ];
    }
}
