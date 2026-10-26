<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckInventoryTotals extends Command
{
    protected $signature = 'inventory:check-totals {--fix : Set quantity_on_hand to the bin total for every drifted product}';

    protected $description = 'List products whose quantity_on_hand differs from the sum of their live storage-location quantities';

    public function handle(): int
    {
        $binTotals = DB::table('inventory_locations')
            ->select('product_id', DB::raw('SUM(quantity) as bin_total'))
            ->whereNull('deleted_at')
            ->groupBy('product_id');

        $drifted = DB::table('products')
            ->leftJoinSub($binTotals, 'b', 'b.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->whereRaw('ABS(products.quantity_on_hand - COALESCE(b.bin_total, 0)) > 0.0001')
            ->get(['products.id', 'products.sku', 'products.quantity_on_hand', DB::raw('COALESCE(b.bin_total, 0) as bin_total')]);

        if ($drifted->isEmpty()) {
            $this->info('Every product total matches its bins.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'SKU', 'quantity_on_hand', 'Bins'], $drifted->map(fn ($r) => [$r->id, $r->sku, $r->quantity_on_hand, $r->bin_total])->all());
        Log::warning('inventory:check-totals found drift', ['products' => $drifted->count()]);

        if ($this->option('fix')) {
            foreach ($drifted as $row) {
                $product = Product::find($row->id);
                $product?->recalculateQuantitiesFromLocations();
            }
            $this->info("Recalculated {$drifted->count()} product(s) from their bins.");

            return self::SUCCESS;
        }

        $this->warn("{$drifted->count()} product(s) drifted. Re-run with --fix to recalculate from the bins.");

        return self::FAILURE;
    }
}
