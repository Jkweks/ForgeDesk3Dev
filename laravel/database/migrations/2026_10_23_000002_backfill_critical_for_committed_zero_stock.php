<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Parts that jobs are holding stock against while none is on hand are now
     * always "critical". Re-run the status rule for the ones already in that state
     * (they were out_of_stock / low / etc. when the old rule needed a reorder point).
     */
    public function up(): void
    {
        $ids = DB::table('products')
            ->where('quantity_on_hand', '<=', 0)
            ->where('status', '!=', 'critical')
            ->whereNull('deleted_at')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('job_reservation_items as ri')
                    ->join('job_reservations as r', 'ri.reservation_id', '=', 'r.id')
                    ->whereColumn('ri.product_id', 'products.id')
                    ->whereIn('r.status', ['active', 'in_progress', 'on_hold'])
                    ->whereNull('r.deleted_at')
                    ->whereRaw('ri.committed_qty > ri.consumed_qty');
            })
            ->pluck('id');

        Product::whereIn('id', $ids)->get()->each->updateStatus();
    }

    public function down(): void
    {
        // Data refresh only — statuses are recalculated from live quantities anyway.
    }
};
