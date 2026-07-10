<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\JobReservationItem;

class SyncCommittedFromReservations extends Command
{
    protected $signature = 'inventory:sync-committed
                            {--product= : Limit to a specific product ID}
                            {--dry-run  : Show what would change without writing anything}';

    protected $description = 'Sync quantity_committed on products and inventory_locations from active unfulfilled reservations';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written.');
        }

        $this->info('Syncing committed quantities from active reservations...');
        $this->newLine();

        $query = Product::query();

        if ($productId = $this->option('product')) {
            $query->where('id', $productId);
        }

        $products = $query->get();

        $productChanged = 0;

        foreach ($products as $product) {
            $totalCommitted = JobReservationItem::binAwareCommitted($product->id);

            $oldProductCommitted = $product->quantity_committed;

            if ($oldProductCommitted != $totalCommitted) {
                $this->line(sprintf(
                    '  %s: quantity_committed %s → %s',
                    $product->sku, $oldProductCommitted, $totalCommitted
                ));

                if (!$dryRun) {
                    $product->quantity_committed = $totalCommitted;
                    $product->save();
                }

                $productChanged++;
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn("DRY RUN complete — {$productChanged} product(s) would change.");
        } else {
            $this->info("✓ Done — {$productChanged} product(s) updated.");
        }

        return 0;
    }
}
