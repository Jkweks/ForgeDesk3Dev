<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditInventoryTransactionMismatch extends Command
{
    protected $signature = 'inventory:audit-transaction-mismatch
                            {--product= : Limit to a specific product ID}';

    protected $description = 'List products where sum of storage location quantities does not match the quantity_after of their most recent inventory transaction';

    public function handle()
    {
        // Latest transaction per product via a lateral/subquery
        $latestTx = DB::table('inventory_transactions as t1')
            ->select('t1.product_id', 't1.quantity_after', 't1.transaction_date', 't1.type', 't1.reference_number')
            ->whereRaw('t1.id = (
                SELECT t2.id FROM inventory_transactions t2
                WHERE t2.product_id = t1.product_id
                ORDER BY t2.transaction_date DESC, t2.id DESC
                LIMIT 1
            )');

        // Sum of location quantities per product (excluding soft-deleted locations)
        $locationTotals = DB::table('inventory_locations')
            ->select('product_id', DB::raw('COALESCE(SUM(quantity), 0) as location_total'))
            ->whereNull('deleted_at')
            ->groupBy('product_id');

        $query = DB::table('products')
            ->select([
                'products.id',
                'products.sku',
                'products.description',
                'products.quantity_on_hand',
                DB::raw('COALESCE(loc.location_total, 0) as location_total'),
                'tx.quantity_after as last_tx_quantity_after',
                'tx.transaction_date as last_tx_date',
                'tx.type as last_tx_type',
                'tx.reference_number as last_tx_reference',
            ])
            ->leftJoinSub($locationTotals, 'loc', 'loc.product_id', '=', 'products.id')
            ->leftJoinSub($latestTx, 'tx', 'tx.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->whereNotNull('tx.quantity_after')
            ->whereRaw('COALESCE(loc.location_total, 0) != tx.quantity_after')
            ->orderBy('products.sku');

        if ($productId = $this->option('product')) {
            $query->where('products.id', $productId);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info('No mismatches found.');
            return 0;
        }

        $this->warn("Found {$rows->count()} mismatch(es):");
        $this->newLine();

        $this->table(
            ['ID', 'SKU', 'Description', 'Location Sum', 'Last Tx qty_after', 'Diff', 'Last Tx Date', 'Tx Type', 'Reference'],
            $rows->map(fn($r) => [
                $r->id,
                $r->sku,
                str($r->description)->limit(40),
                $r->location_total,
                $r->last_tx_quantity_after,
                $r->location_total - $r->last_tx_quantity_after,
                $r->last_tx_date,
                $r->last_tx_type,
                $r->last_tx_reference ?? '—',
            ])
        );

        return 0;
    }
}
