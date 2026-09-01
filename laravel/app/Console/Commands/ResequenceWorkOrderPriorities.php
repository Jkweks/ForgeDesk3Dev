<?php

namespace App\Console\Commands;

use App\Models\FdWorkOrder;
use Illuminate\Console\Command;

/**
 * Rebuilds the work-order priority ranking from due dates. Runs nightly so a
 * newly-due job floats up without anyone touching the queue; hand-pinned
 * (priority_locked) work orders are left where they are.
 */
class ResequenceWorkOrderPriorities extends Command
{
    protected $signature = 'fd:resequence-priorities {--clear-locks : Also unpin every hand-locked work order first}';

    protected $description = 'Rebuild fd_work_orders.priority from due dates (locked rows keep their slot)';

    public function handle(): int
    {
        if ($this->option('clear-locks')) {
            FdWorkOrder::where('archived', false)->update(['priority_locked' => false]);
            $this->info('Cleared all priority locks.');
        }

        FdWorkOrder::resequencePriorities();
        $this->info('Work-order priorities resequenced.');

        return self::SUCCESS;
    }
}
