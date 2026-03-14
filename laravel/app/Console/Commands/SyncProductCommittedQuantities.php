<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\JobReservationItem;

class SyncProductCommittedQuantities extends Command
{
    protected $signature = 'products:sync-committed';
    protected $description = 'Sync products.quantity_committed with sum of active reservation_items';

    public function handle()
    {
        $this->info('Syncing product committed quantities from reservation items...');

        $products = Product::where('is_active', true)->get();
        $synced = 0;
        $changed = 0;

        foreach ($products as $product) {
            $oldCommitted = $product->quantity_committed;

            // Calculate total committed from all ACTIVE reservations
            $totalCommitted = JobReservationItem::where('product_id', $product->id)
                ->whereHas('reservation', function($query) {
                    $query->whereIn('status', ['active', 'in_progress', 'on_hold'])
                          ->whereNull('deleted_at');
                })
                ->sum('committed_qty');

            $product->quantity_committed = $totalCommitted;
            $product->save();

            if ($oldCommitted != $totalCommitted) {
                $this->line("  {$product->sku}: {$oldCommitted} → {$totalCommitted}");
                $changed++;
            }

            $synced++;
        }

        $this->info("✓ Synced {$synced} products, {$changed} changed.");
        return 0;
    }
}
