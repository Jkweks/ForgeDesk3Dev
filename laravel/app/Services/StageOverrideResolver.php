<?php

namespace App\Services;

use App\Models\FdJobStep;
use App\Models\FdStageLog;
use App\Models\FdUser;
use App\Models\FdWoStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Decides whether the caller may override a step gate and, if so, builds the
 * logger closure that records the override.
 *
 * Two trust contexts:
 *  - Office: an authenticated app user holding `fabrication.work-orders.edit`.
 *  - Kiosk (unauthenticated): the `fab_user_id` in the body resolves to an
 *    `FdUser` whose role is `manager` or `admin`.
 */
class StageOverrideResolver
{
    /**
     * @return array{allowed: bool, log_user_id: ?int, actor_label: string}
     */
    public function resolve(Request $request): array
    {
        $wants = $request->boolean('override');

        $appUser = $request->user();
        if ($appUser && method_exists($appUser, 'hasPermission')
            && $appUser->hasPermission('fabrication.work-orders.edit')) {
            return [
                'allowed'     => $wants,
                // fd_stage_log.user_id FKs fd_users, not users — keep null, name in message.
                'log_user_id' => null,
                'actor_label' => trim(($appUser->name ?? 'Office user')) . ' (office)',
            ];
        }

        $fabUserId = $request->input('fab_user_id');
        if ($fabUserId) {
            $fabUser = FdUser::find($fabUserId);
            if ($fabUser && in_array($fabUser->role, ['manager', 'admin'], true)) {
                return [
                    'allowed'     => $wants,
                    'log_user_id' => $fabUser->id,
                    'actor_label' => $fabUser->name,
                ];
            }
        }

        return ['allowed' => false, 'log_user_id' => null, 'actor_label' => ''];
    }

    /**
     * Logger closure for StageGateService::guardStageTransition().
     */
    public function stageLogger(FdWoStage $stage, array $resolution): callable
    {
        return function ($blocking) use ($stage, $resolution) {
            FdStageLog::create([
                'stage_id' => $stage->id,
                'user_id'  => $resolution['log_user_id'],
                'message'  => sprintf(
                    'Gate overridden by %s: proceeded past "%s"',
                    $resolution['actor_label'] ?: 'unknown',
                    $blocking->name
                ),
            ]);
        };
    }

    /**
     * Logger closure for job steps — no per-row log table, so record to the app
     * log. The controller also echoes the override in its JSON response.
     */
    public function jobStepLogger(FdJobStep $step, array $resolution): callable
    {
        return function ($blocking) use ($step, $resolution) {
            Log::info('Job-step gate overridden', [
                'work_order_id' => $step->work_order_id,
                'step_id'       => $step->id,
                'step'          => $step->name,
                'blocking_step' => $blocking->name,
                'by'            => $resolution['actor_label'] ?: 'unknown',
            ]);
        };
    }
}
