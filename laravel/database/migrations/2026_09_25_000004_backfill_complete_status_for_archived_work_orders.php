<?php

use App\Models\FdJobStep;
use App\Models\FdWoElevation;
use App\Models\FdWorkOrder;
use App\Models\FdWoStage;
use App\Models\FdWoStatusLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;

/**
 * Data backfill: some work orders were archived without ever being carried
 * through the normal "complete" transition (WorkOrderController::updateStatus()),
 * so they're sitting with archived=true but status still active/on_hold and
 * completed_at null. This closes them out as of 2026-08-31 — the same fields
 * a real completion sets (status, completed_at), plus cascading completion
 * down to their checklist steps, elevations, and elevation stages so the
 * work order reads as fully done everywhere isComplete()/isReadyToComplete()
 * are checked, not just on the top-level record.
 */
return new class extends Migration
{
    public function up(): void
    {
        $completedAt = Carbon::parse('2026-08-31 00:00:00');

        FdWorkOrder::where('archived', true)
            ->whereNull('completed_at')
            ->chunkById(100, function ($workOrders) use ($completedAt) {
                foreach ($workOrders as $wo) {
                    $fromStatus = $wo->status;

                    FdJobStep::where('work_order_id', $wo->id)
                        ->whereNotIn('status', FdJobStep::TERMINAL)
                        ->update(['status' => 'complete', 'completed_at' => $completedAt]);

                    $elevationIds = FdWoElevation::where('work_order_id', $wo->id)->pluck('id');

                    FdWoElevation::whereIn('id', $elevationIds)
                        ->whereNull('date_completed')
                        ->update(['date_completed' => $completedAt]);

                    FdWoStage::where('work_order_id', $wo->id)
                        ->whereNotIn('status', FdWoStage::TERMINAL)
                        ->update(['status' => 'complete', 'completed_at' => $completedAt]);

                    $wo->status = 'complete';
                    $wo->completed_at = $completedAt;
                    $wo->save();

                    FdWoStatusLog::create([
                        'work_order_id' => $wo->id,
                        'user_id' => null,
                        'from_status' => $fromStatus,
                        'to_status' => 'complete',
                        'note' => 'Backfilled complete (dated 2026-08-31) for an already-archived work order with no completion date — migration 2026_09_25_000004.',
                    ]);

                    $wo->businessJob?->syncAutoStatus();
                }
            });
    }

    public function down(): void
    {
        // Irreversible by design: this is a historical data correction, not
        // a structural change, and there's no reliable way to tell which of
        // the affected rows were genuinely incomplete-but-forgotten versus
        // something a rollback should restore to a prior (unknown) state.
    }
};
