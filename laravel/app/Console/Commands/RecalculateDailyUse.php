<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class RecalculateDailyUse extends Command
{
    protected $signature = 'inventory:recalculate-daily-use
                            {--start-date=2026-01-01 : Earliest transaction date to include}
                            {--product= : Comma-separated product IDs to update (omit for all)}';

    protected $description = 'Recalculate average_daily_use for all products from outbound transaction history';

    public function handle(): int
    {
        $startDate  = $this->option('start-date');
        $productOpt = $this->option('product');
        $productIds = $productOpt
            ? array_map('intval', explode(',', $productOpt))
            : null;

        $scope = $productIds ? implode(', ', $productIds) : 'all products';
        $this->info("Recalculating average daily use for {$scope} from {$startDate}…");

        Product::recalculateDailyUse($productIds, $startDate);

        $this->info('Done.');
        return self::SUCCESS;
    }
}
