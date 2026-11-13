<?php

namespace App\Services\CutFlow;

use App\Models\CutFlow\CutJob;
use App\Models\CutFlow\Part;
use App\Models\DoorFrameConfiguration;
use App\Models\FdStageLog;
use App\Models\FdWoElevation;
use App\Models\FdWoStage;

/**
 * Closes an elevation's "Cut" step once every cut-list line for its door/frame configuration is cut.
 *
 * A configuration's lines are the parts on its work order's cut job whose `phase` is the
 * configuration's door tags joined with "/" (see CutFlowExportService::buildRows). Only moves a
 * step forward: a step already complete/not_required is left alone, and re-queuing a piece
 * (recut) does not reopen it.
 */
class CutStepSync
{
    /** Call after any change that drew down a part's qty_remaining. */
    public static function afterPartCut(?Part $part): void
    {
        if (! $part || $part->qty_remaining > 0 || ! $part->phase) {
            return;
        }

        $workOrderId = CutJob::whereKey($part->cut_job_id)->value('work_order_id');
        if (! $workOrderId) {
            return;
        }

        $parts = Part::whereIn('cut_job_id', CutJob::where('work_order_id', $workOrderId)->pluck('id'))
            ->where('phase', $part->phase)
            ->get(['id', 'qty_remaining']);
        if ($parts->sum('qty_remaining') > 0) {
            return;
        }

        $configIds = DoorFrameConfiguration::with('doors')
            ->where('work_order_id', $workOrderId)
            ->get()
            ->filter(fn ($c) => $c->doors->pluck('door_tag')->implode('/') === $part->phase)
            ->pluck('id');
        if ($configIds->isEmpty()) {
            return;
        }

        $elevationIds = FdWoElevation::where('work_order_id', $workOrderId)
            ->whereIn('door_frame_configuration_id', $configIds)->pluck('id');

        FdWoStage::whereIn('elevation_id', $elevationIds)
            ->whereRaw('lower(name) = ?', ['cut'])
            ->whereNotIn('status', FdWoStage::TERMINAL)
            ->get()
            ->each(function (FdWoStage $stage) {
                $stage->update(['status' => 'complete', 'completed_at' => now(), 'started_at' => $stage->started_at ?? now()]);
                FdStageLog::create([
                    'stage_id' => $stage->id,
                    'user_id' => null,
                    'message' => 'Cut step completed automatically: cut list finished.',
                ]);
            });
    }
}
