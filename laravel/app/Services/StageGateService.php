<?php

namespace App\Services;

use App\Exceptions\StageGatedException;
use App\Models\FdJobStep;
use App\Models\FdWoStage;
use Illuminate\Support\Collection;

/**
 * The single source of truth for step gating.
 *
 * Elevation stages gate per-phase: a stage is blocked while any sibling in the
 * same elevation that is flagged `blocks_next`, sits in an *earlier* phase, and
 * has not reached a terminal status. A stage's phase is its explicit `phase`
 * column, or its `sort_order` when that is null — so stages sharing a phase run
 * concurrently and never gate each other. Non-blocking predecessors are ignored.
 *
 * Job steps (the flat WO checklist) gate strict-sequentially: every earlier
 * step must be terminal.
 */
class StageGateService
{
    public const TERMINAL = ['complete', 'not_required'];

    public const ACTIVE = ['in_progress', 'complete'];

    public function blockingStageFor(FdWoStage $stage): ?FdWoStage
    {
        if ($stage->elevation_id === null) {
            return null; // legacy WO-scoped stages carry no elevation ordering
        }

        $myPhase = $stage->phase ?? $stage->sort_order;

        $siblings = FdWoStage::query()
            ->where('elevation_id', $stage->elevation_id)
            ->where('id', '!=', $stage->id)
            ->where('blocks_next', true)
            ->whereRaw('COALESCE(phase, sort_order) < ?', [$myPhase])
            ->orderByRaw('COALESCE(phase, sort_order)')
            ->get(['id', 'name', 'status', 'sort_order', 'phase']);

        return $this->firstNonTerminal($siblings);
    }

    /**
     * blockingStageFor() evaluated against an already-loaded sibling collection
     * (an elevation's `stages` relation) — use in list endpoints to avoid N+1.
     */
    public function blockingStageForLoaded(FdWoStage $stage, Collection $siblings): ?FdWoStage
    {
        if ($stage->elevation_id === null) {
            return null;
        }

        $myPhase = $stage->phase ?? $stage->sort_order;

        $candidates = $siblings
            ->filter(fn ($s) => $s->id !== $stage->id
                && $s->blocks_next
                && ($s->phase ?? $s->sort_order) < $myPhase)
            ->sortBy(fn ($s) => $s->phase ?? $s->sort_order);

        return $this->firstNonTerminal($candidates);
    }

    /**
     * A second, independent gate alongside the phase-based one above: an
     * elevation tied to a configurator opening (DoorFrameConfiguration)
     * can't start production until that opening is released. No linked
     * configuration at all (an elevation predating this feature, or one
     * never configurator-tracked) is never gated by this check.
     */
    public function blockingConfigurationReasonFor(FdWoStage $stage): ?string
    {
        $config = $stage->elevation?->doorFrameConfiguration;
        if (! $config) {
            return null;
        }

        if (in_array($config->status, ['released', 'in_progress', 'completed'], true)) {
            return null;
        }

        return "Configurator: opening is still {$config->status_label} — release it before starting production.";
    }

    public function blockingJobStepFor(FdJobStep $step): ?FdJobStep
    {
        $earlier = FdJobStep::query()
            ->where('work_order_id', $step->work_order_id)
            ->where('id', '!=', $step->id)
            ->where('sort_order', '<', $step->sort_order)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'status', 'sort_order']);

        return $this->firstNonTerminal($earlier);
    }

    /**
     * Enforce the gate for a stage moving to $toStatus. No-op unless the move is
     * an activation. When blocked: throw StageGatedException unless $override, in
     * which case $logOverride($blockingStage) is invoked and the move proceeds.
     */
    public function guardStageTransition(FdWoStage $stage, string $toStatus, bool $override, ?callable $logOverride = null): void
    {
        if (! $this->isActivation($stage->status, $toStatus)) {
            return;
        }

        // Sentinel id 0 stands in for "the configurator" as the blocker —
        // reuses StageGatedException's existing shape (id/name) so the
        // frontend's error handling renders it with no changes.
        if ($reason = $this->blockingConfigurationReasonFor($stage)) {
            if (! $override) {
                throw new StageGatedException(0, 'Configurator', $reason);
            }
            if ($logOverride) {
                $logOverride((object) ['id' => 0, 'name' => 'Configurator']);
            }
        }

        $blocking = $this->blockingStageFor($stage);
        if ($blocking === null) {
            return;
        }

        if (! $override) {
            throw new StageGatedException($blocking->id, $blocking->name);
        }

        if ($logOverride) {
            $logOverride($blocking);
        }
    }

    public function guardJobStepTransition(FdJobStep $step, string $toStatus, bool $override, ?callable $logOverride = null): void
    {
        if (! $this->isActivation($step->status, $toStatus)) {
            return;
        }

        $blocking = $this->blockingJobStepFor($step);
        if ($blocking === null) {
            return;
        }

        if (! $override) {
            throw new StageGatedException($blocking->id, $blocking->name);
        }

        if ($logOverride) {
            $logOverride($blocking);
        }
    }

    /**
     * True when $to is an active/terminal-active state reached from something
     * that wasn't. Completion always re-checks (an earlier step may have been
     * reopened after this one started).
     */
    public function isActivation(?string $from, string $to): bool
    {
        if (! in_array($to, self::ACTIVE, true)) {
            return false;
        }
        if ($to === 'complete') {
            return true;
        }

        return ! in_array($from, self::ACTIVE, true);
    }

    private function firstNonTerminal(Collection $items): mixed
    {
        foreach ($items as $item) {
            if (! in_array($item->status, self::TERMINAL, true)) {
                return $item;
            }
        }

        return null;
    }
}
