<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;

class SyncProductOnHandFromLocations extends Command
{
    protected $signature = 'products:sync-on-hand
                            {--product= : Specific product ID to sync (omit for all)}';

    protected $description = 'Set quantity_on_hand to the sum of all inventory location quantities and recalculate status';

    public function handle()
    {
        $query = Product::withoutGlobalScopes();

        if ($productId = $this->option('product')) {
            $query->where('id', $productId);
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            $this->warn('No products found.');
            return 1;
        }

        $this->info("Syncing on-hand quantities from inventory locations for {$products->count()} product(s)...");

        $synced  = 0;
        $changed = 0;

        foreach ($products as $product) {
            $oldOnHand = $product->quantity_on_hand;
            $oldStatus = $product->status;

            $product->recalculateQuantitiesFromLocations();

            if ($oldOnHand != $product->quantity_on_hand || $oldStatus !== $product->status) {
                $this->line(sprintf(
                    '  [%s] %s: on_hand %s → %s | status %s → %s',
                    $product->id,
                    $product->sku,
                    $oldOnHand,
                    $product->quantity_on_hand,
                    $oldStatus,
                    $product->status,
                ));
                $changed++;
            }

            $synced++;
        }

        $this->info("✓ Synced {$synced} products, {$changed} changed.");
        return 0;
    }
}
