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

        $query = Product::with(['inventoryLocations']);

        if ($productId = $this->option('product')) {
            $query->where('id', $productId);
        }

        $products = $query->get();

        $productChanged  = 0;
        $locationChanged = 0;

        foreach ($products as $product) {
            // ── 1. Calculate total committed from active, unfulfilled reservations ──────
            $totalCommitted = JobReservationItem::where('product_id', $product->id)
                ->whereHas('reservation', function ($q) {
                    $q->whereIn('status', ['active', 'in_progress', 'on_hold'])
                      ->whereNull('deleted_at');
                })
                ->sum('committed_qty');

            // ── 2. Sync product.quantity_committed ────────────────────────────────────
            $oldProductCommitted = $product->quantity_committed;

            if ($oldProductCommitted != $totalCommitted) {
                $this->line(sprintf(
                    '  [product] %s: quantity_committed %s → %s',
                    $product->sku, $oldProductCommitted, $totalCommitted
                ));

                if (!$dryRun) {
                    $product->quantity_committed = $totalCommitted;
                    $product->save();
                }

                $productChanged++;
            }

            // ── 3. Distribute committed qty across inventory_locations ────────────────
            //   Waterfall: fill primary location first (up to its physical quantity),
            //   then overflow into secondary locations ordered by quantity descending.
            //   Any location that receives no allocation gets quantity_committed = 0.

            $locations = $product->inventoryLocations
                ->sortByDesc('is_primary')          // primary first
                ->sortByDesc('quantity')             // then by stock descending
                ->values();

            $remaining = $totalCommitted;

            foreach ($locations as $location) {
                $allocated = min($remaining, $location->quantity);
                $allocated = max(0, $allocated); // never negative

                $oldLocationCommitted = $location->quantity_committed;

                if ($oldLocationCommitted != $allocated) {
                    $this->line(sprintf(
                        '    [location] %s / loc#%d: quantity_committed %s → %s',
                        $product->sku, $location->id, $oldLocationCommitted, $allocated
                    ));

                    if (!$dryRun) {
                        $location->quantity_committed = $allocated;
                        $location->save();
                    }

                    $locationChanged++;
                }

                $remaining -= $allocated;
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn("DRY RUN complete — {$productChanged} product(s) and {$locationChanged} location row(s) would change.");
        } else {
            $this->info("✓ Done — {$productChanged} product(s) and {$locationChanged} location row(s) updated.");
        }

        return 0;
    }
}
