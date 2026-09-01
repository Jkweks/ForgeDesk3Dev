<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdWoElevation;
use App\Models\FdWoStage;
use App\Models\FdStageTemplate;
use App\Models\FdStageTemplateSet;
use App\Models\FdWorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ElevationController extends Controller
{
    public function index(int $workOrderId)
    {
        $wo = FdWorkOrder::findOrFail($workOrderId);
        $elevations = $wo->elevations()
            ->with(['elevationType', 'completedBy', 'stages.assignedTo'])
            ->get()
            ->map(fn($e) => $this->formatElevation($e));

        return response()->json(['elevations' => $elevations]);
    }

    public function store(Request $request, int $workOrderId)
    {
        $request->validate([
            'elevation_tag'     => 'required|string|max:100',
            'elevation_type_id' => 'nullable|integer|exists:fd_elevation_types,id',
            'template_set_id'   => 'nullable|integer|exists:fd_stage_template_sets,id',
        ]);

        FdWorkOrder::findOrFail($workOrderId);

        try {
            DB::beginTransaction();

            // Resolve which complexity tier to seed from.
            $setId = null;
            if ($request->elevation_type_id) {
                $setId = $request->template_set_id
                    ?? FdStageTemplateSet::where('elevation_type_id', $request->elevation_type_id)
                        ->where('is_default', true)->value('id')
                    ?? FdStageTemplateSet::where('elevation_type_id', $request->elevation_type_id)
                        ->orderBy('sort_order')->value('id');
            }

            $elevation = FdWoElevation::create([
                'work_order_id'     => $workOrderId,
                'elevation_type_id' => $request->elevation_type_id,
                'template_set_id'   => $setId,
                'elevation_tag'     => $request->elevation_tag,
                'quantity'          => $request->quantity ?? 1,
                'date_requested'    => $request->date_requested,
                'notes'             => $request->notes,
                'scope'             => $request->scope ?? 'assemble',
            ]);

            // Auto-seed stages from the chosen tier's templates
            if ($setId) {
                $templates = FdStageTemplate::where('template_set_id', $setId)
                    ->orderBy('sort_order')
                    ->get();

                foreach ($templates as $tpl) {
                    FdWoStage::create([
                        'elevation_id'   => $elevation->id,
                        'work_order_id'  => null,
                        'template_id'    => $tpl->id,
                        'name'           => $tpl->name,
                        'description'    => $tpl->description,
                        'sort_order'     => $tpl->sort_order,
                        'blocks_next'    => $tpl->blocks_next ?? true,
                        'status'         => 'pending',
                        'assigned_to_id' => $tpl->default_user_id,
                    ]);
                }
            }

            DB::commit();

            $elevation->load(['elevationType', 'completedBy', 'stages.assignedTo']);
            return response()->json($this->formatElevation($elevation), 201);
        } catch (\Exception $e) {
            DB::rollBack();
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
                'elevation_type_id', 'template_set_id', 'elevation_tag', 'quantity',
                'date_requested', 'date_completed', 'completed_by_id', 'notes', 'scope',
            ]));

            $newSetId = $elevation->template_set_id;
            $resyncSummary = null;

            DB::transaction(function () use ($elevation, $request, $oldSetId, $newSetId, &$resyncSummary) {
                // Bumping (or switching) the tier reconciles the stage list.
                if ($request->has('template_set_id') && $newSetId && (int) $newSetId !== (int) $oldSetId) {
                    $resyncSummary = $this->resyncStagesToSet($elevation, (int) $newSetId);
                }
                $elevation->save();
            });

            $elevation->load(['elevationType', 'completedBy', 'stages.assignedTo', 'stages.completedBy']);
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
     *  - matched stages keep their status/assignee/timestamps; only sort_order
     *    and blocks_next are re-pulled from the template
     *  - templates with no match are added as fresh `pending` stages
     *  - existing stages absent from the new tier: retired to `not_required` if
     *    untouched, left alone if they already have progress
     *
     * @return array{added: string[], carried: string[], retired: string[], kept_with_progress: string[]}
     */
    private function resyncStagesToSet(FdWoElevation $elevation, int $newSetId): array
    {
        $templates = FdStageTemplate::where('template_set_id', $newSetId)->orderBy('sort_order')->get();
        $existing  = $elevation->stages()->get();

        $added = $carried = $retired = $keptWithProgress = [];
        $keepIds = [];

        foreach ($templates as $tpl) {
            $match = $existing->first(fn($s) => $s->template_id === $tpl->id && ! in_array($s->id, $keepIds, true))
                ?? $existing->first(fn($s) => mb_strtolower($s->name) === mb_strtolower($tpl->name) && ! in_array($s->id, $keepIds, true));

            if ($match) {
                $match->sort_order  = $tpl->sort_order;
                $match->blocks_next = $tpl->blocks_next ?? true;
                $match->template_id = $tpl->id;
                $match->save();
                $keepIds[] = $match->id;
                $carried[] = $match->name;
            } else {
                $stage = FdWoStage::create([
                    'elevation_id'   => $elevation->id,
                    'work_order_id'  => null,
                    'template_id'    => $tpl->id,
                    'name'           => $tpl->name,
                    'description'    => $tpl->description,
                    'sort_order'     => $tpl->sort_order,
                    'blocks_next'    => $tpl->blocks_next ?? true,
                    'status'         => 'pending',
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
            'added'              => $added,
            'carried'            => $carried,
            'retired'            => $retired,
            'kept_with_progress' => $keptWithProgress,
        ];
    }

    public function destroy(int $id)
    {
        $elevation = FdWoElevation::findOrFail($id);
        $elevation->delete();
        return response()->json(['deleted' => $id]);
    }

    private function formatElevation(FdWoElevation $e): array
    {
        $stages = $e->relationLoaded('stages') ? $e->stages : $e->stages()->with(['assignedTo', 'completedBy'])->get();
        $stageCount    = $stages->count();
        $stagesDone    = $stages->whereIn('status', ['complete', 'not_required'])->count();
        $stagesActive  = $stages->where('status', 'in_progress')->count();
        $stagesBlocked = $stages->where('status', 'blocked')->count();

        return [
            'id'                => $e->id,
            'work_order_id'     => $e->work_order_id,
            'elevation_type_id' => $e->elevation_type_id,
            'template_set_id'   => $e->template_set_id,
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
            'stage_count'       => $stageCount,
            'stages_done'       => $stagesDone,
            'stages_active'     => $stagesActive,
            'stages_blocked'    => $stagesBlocked,
            'stages'            => $stages->map(fn($s) => [
                'id'                => $s->id,
                'name'              => $s->name,
                'status'            => $s->status,
                'sort_order'        => $s->sort_order,
                'blocks_next'       => (bool) $s->blocks_next,
                'assigned_name'     => $s->assignedTo?->name,
                'completed_by_id'   => $s->completed_by_id,
                'completed_by_name' => $s->completedBy?->name,
                'started_at'        => $s->started_at?->toIso8601String(),
                'completed_at'      => $s->completed_at?->toIso8601String(),
            ])->values(),
            'created_at'        => $e->created_at->toIso8601String(),
            'updated_at'        => $e->updated_at->toIso8601String(),
        ];
    }
}
