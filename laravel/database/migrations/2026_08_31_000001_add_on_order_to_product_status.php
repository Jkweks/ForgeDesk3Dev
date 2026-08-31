<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add 'on_order' to the products.status value set.
     *
     * A product in a shortage tier (low / very_low / critical / out_of_stock)
     * whose inbound purchase-order quantity already clears the reorder point is
     * now tagged 'on_order' by Product::updateStatus(), so buyers can tell
     * "replenishment already placed" from "act now".
     *
     * This migration only widens the constraint. Existing rows are re-tagged the
     * next time each product's status is recalculated — run Admin → Inventory →
     * "Refresh All Statuses" once after deploy to apply it across the catalog
     * (that action also re-derives on_order_qty from live POs).
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('products', function (Blueprint $table) {
                $table->enum('status', ['in_stock', 'low', 'very_low', 'critical', 'out_of_stock', 'on_order'])
                    ->default('in_stock')->change();
            });
        } else {
            DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_status_check');
            DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status IN ('in_stock', 'low', 'very_low', 'critical', 'out_of_stock', 'on_order'))");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        // Fold any on_order rows back into a shortage tier so nothing violates the
        // narrower constraint. very_low is the safe floor; the next status
        // recalculation will correct it.
        DB::statement("UPDATE products SET status = 'very_low' WHERE status = 'on_order'");

        if ($driver === 'sqlite') {
            Schema::table('products', function (Blueprint $table) {
                $table->enum('status', ['in_stock', 'low', 'very_low', 'critical', 'out_of_stock'])
                    ->default('in_stock')->change();
            });
        } else {
            DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_status_check');
            DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status IN ('in_stock', 'low', 'very_low', 'critical', 'out_of_stock'))");
        }
    }
};
