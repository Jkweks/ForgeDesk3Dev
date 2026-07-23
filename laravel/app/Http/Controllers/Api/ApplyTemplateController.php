<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdStageTemplate;
use App\Models\FdStageTemplateDep;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Models\FdWoStage;
use App\Models\FdWoStageDep;
use App\Services\StageStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApplyTemplateController extends Controller
{
    private const SKIP_STATUSES = ['complete', 'in_progress'];

    /** Bulk-apply template stages to all elevations in a work order (merge — keeps completed/in-progress). */
    public function applyToWorkOrder(int $workOrderId)
    {
        $wo = FdWorkOrder::findOrFail($workOrderId);

        try {
            DB::beginTransaction();

            $elevations = $wo->elevations()->get();
            foreach ($elevations as $elevation) {
                $this->applyToElevationModel($elevation);
            }

            DB::commit();
            return response()->json(['applied' => $elevations->count()]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ApplyTemplateController@applyToWorkOrder failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to apply template path'], 500);
        }
    }

    /** Apply template stages to a single elevation (merge — keeps completed/in-progress). */
    public function applyToElevation(int $elevationId)
    {
        $elevation = FdWoElevation::findOrFail($elevationId);

        try {
            DB::beginTransaction();
            $this->applyToElevationModel($elevation);
            DB::commit();
            return response()->json(['applied' => $elevationId]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ApplyTemplateController@applyToElevation failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to apply template stages'], 500);
        }
    }

    private function applyToElevationModel(FdWoElevation $elevation): void
    {
        if (!$elevation->elevation_type_id) {
            return;
        }

        $templates = FdStageTemplate::where('elevation_type_id', $elevation->elevation_type_id)
            ->orderBy('sort_order')
            ->get();

        if ($templates->isEmpty()) {
            return;
        }

        // Build map of existing stages by template_id
        $existingByTemplate = FdWoStage::where('elevation_id', $elevation->id)
            ->whereNotNull('template_id')
            ->get()
            ->keyBy('template_id');

        $templateToStage = [];

        foreach ($templates as $tpl) {
            $existing = $existingByTemplate->get($tpl->id);

            if ($existing) {
                // Keep the existing stage — record its ID for dep syncing
                $templateToStage[$tpl->id] = $existing->id;
                continue;
            }

            // Stage is missing — create it
            $maxOrder = FdWoStage::where('elevation_id', $elevation->id)->max('sort_order') ?? 0;

            $stage = FdWoStage::create([
                'elevation_id'   => $elevation->id,
                'work_order_id'  => null,
                'template_id'    => $tpl->id,
                'name'           => $tpl->name,
                'description'    => $tpl->description,
                'sort_order'     => $maxOrder + 1,
                'status'         => 'pending',
                'assigned_to_id' => $tpl->default_user_id,
            ]);

            $templateToStage[$tpl->id] = $stage->id;
        }

        // Sync dep pairs: add any missing FdWoStageDep rows (don't remove existing)
        $templateIds  = $templates->pluck('id');
        $templateDeps = FdStageTemplateDep::whereIn('template_id', $templateIds)
            ->whereIn('depends_on_template_id', $templateIds)
            ->get();

        foreach ($templateDeps as $dep) {
            $stageId    = $templateToStage[$dep->template_id] ?? null;
            $depStageId = $templateToStage[$dep->depends_on_template_id] ?? null;
            if ($stageId && $depStageId) {
                FdWoStageDep::firstOrCreate([
                    'stage_id'            => $stageId,
                    'depends_on_stage_id' => $depStageId,
                ]);
            }
        }

        StageStatusService::recomputeElevation($elevation->id);
    }
}
