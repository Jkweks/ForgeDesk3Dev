<?php

namespace App\Services;

use App\Models\FdWoStage;
use App\Models\FdWoStageDep;

class StageStatusService
{
    private const TERMINAL = ['complete', 'not_required'];
    private const SKIP     = ['in_progress', 'complete', 'not_required', 'on_hold'];

    public static function recomputeElevation(int $elevationId): void
    {
        $stages   = FdWoStage::where('elevation_id', $elevationId)->get();
        $stageMap = $stages->keyBy('id');
        $stageIds = $stages->pluck('id');

        $allDeps   = FdWoStageDep::whereIn('stage_id', $stageIds)->get();
        $depsByStage = $allDeps->groupBy('stage_id');

        foreach ($stages as $stage) {
            if (in_array($stage->status, self::SKIP)) {
                continue;
            }

            $deps = $depsByStage->get($stage->id, collect());

            if ($deps->isEmpty()) {
                if ($stage->status !== 'pending' || $stage->blocked_reason !== null) {
                    $stage->status         = 'pending';
                    $stage->blocked_reason = null;
                    $stage->save();
                }
                continue;
            }

            $incomplete = $deps->filter(function ($dep) use ($stageMap) {
                $prereq = $stageMap->get($dep->depends_on_stage_id);
                return !$prereq || !in_array($prereq->status, self::TERMINAL);
            });

            if ($incomplete->isEmpty()) {
                if ($stage->status !== 'pending' || $stage->blocked_reason !== null) {
                    $stage->status         = 'pending';
                    $stage->blocked_reason = null;
                    $stage->save();
                }
            } else {
                $names  = $incomplete->map(fn($d) => $stageMap->get($d->depends_on_stage_id)?->name)->filter()->join(', ');
                $reason = "Waiting: {$names}";
                if ($stage->status !== 'blocked' || $stage->blocked_reason !== $reason) {
                    $stage->status         = 'blocked';
                    $stage->blocked_reason = $reason;
                    $stage->save();
                }
            }
        }
    }
}
