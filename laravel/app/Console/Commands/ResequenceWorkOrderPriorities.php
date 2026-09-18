<?php

namespace App\Console\Commands;

use App\Models\FdWorkOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Rebuilds the work-order priority ranking from due dates. Runs nightly so a
 * newly-due job floats up without anyone touching the queue; hand-pinned
 * (priority_locked) work orders are left where they are.
 *
 * Also sweeps completed work orders: resequencePriorities() itself already
 * clears priority off anything marked complete (they no longer belong in the
 * active queue), and this command additionally auto-archives a completed
 * work order once it's been sitting complete for ARCHIVE_AFTER_DAYS days,
 * so the active list doesn't slowly fill up with old finished work that
 * nobody remembered to archive by hand.
 */
class ResequenceWorkOrderPriorities extends Command
{
    private const ARCHIVE_AFTER_DAYS = 3;

    protected $signature = 'fd:resequence-priorities {--clear-locks : Also unpin every hand-locked work order first}';

    protected $description = 'Rebuild fd_work_orders.priority from due dates (locked rows keep their slot) and auto-archive old completed work orders';

    public function handle(): int
    {
        if ($this->option('clear-locks')) {
            FdWorkOrder::where('archived', false)->update(['priority_locked' => false]);
            $this->info('Cleared all priority locks.');
        }

        FdWorkOrder::resequencePriorities();
        $this->info('Work-order priorities resequenced.');

        $cutoff = Carbon::now()->subDays(self::ARCHIVE_AFTER_DAYS);
        $archived = FdWorkOrder::where('archived', false)
            ->where('status', 'complete')
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', $cutoff)
            ->update(['archived' => true]);

        if ($archived > 0) {
            $this->info("Auto-archived {$archived} work order(s) completed more than ".self::ARCHIVE_AFTER_DAYS.' day(s) ago.');
        }

        return self::SUCCESS;
    }
}
