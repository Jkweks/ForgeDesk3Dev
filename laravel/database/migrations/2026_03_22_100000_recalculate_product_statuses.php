<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand the products.status enum to include 'low' and 'very_low',
     * then recalculate all statuses using the new 5-tier logic:
     *   in_stock   : available > reorder_point
     *   low        : safety_stock < available <= reorder_point
     *   very_low   : 0 < available <= safety_stock
     *   out_of_stock: available <= 0  (or overcommitted with reorder_point = 0)
     *   critical   : available < 0 (overcommitted) AND reorder_point > 0
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // 2. Remap any existing 'low_stock' rows to 'low' so no rows violate the new constraint
        DB::statement("UPDATE products SET status = 'low' WHERE status = 'low_stock'");

        // 1 & 3. Expand the CHECK constraint to the new value set. `ALTER TABLE ...
        // DROP/ADD CONSTRAINT` is Postgres/MySQL syntax; SQLite has no named
        // constraints and needs a column rebuild instead (Laravel's `->change()`
        // handles that for us).
        if ($driver === 'sqlite') {
            // SQLite rebuilds the table to change a column (rename → recreate →
            // copy → drop → rename back), and the inventory_commitments view
            // (which references products) breaks that rebuild since the table
            // briefly doesn't exist under its real name. Drop it around the
            // rebuild, same pattern used elsewhere for this view.
            DB::statement('DROP VIEW IF EXISTS inventory_commitments');
            Schema::table('products', function (Blueprint $table) {
                $table->enum('status', ['in_stock', 'low', 'very_low', 'critical', 'out_of_stock'])
                    ->default('in_stock')->change();
            });
            DB::statement("
                CREATE VIEW inventory_commitments AS
                SELECT
                    p.id AS product_id,
                    p.sku,
                    p.part_number,
                    p.finish,
                    p.description,
                    p.quantity_on_hand AS stock,
                    COALESCE(SUM(
                        CASE
                            WHEN r.status IN ('active', 'in_progress', 'on_hold')
                            THEN ri.committed_qty
                            ELSE 0
                        END
                    ), 0) AS committed_qty,
                    p.quantity_on_hand - COALESCE(SUM(
                        CASE
                            WHEN r.status IN ('active', 'in_progress', 'on_hold')
                            THEN ri.committed_qty
                            ELSE 0
                        END
                    ), 0) AS available_qty
                FROM products p
                LEFT JOIN job_reservation_items ri ON p.id = ri.product_id
                LEFT JOIN job_reservations r ON ri.reservation_id = r.id AND r.deleted_at IS NULL
                GROUP BY p.id
            ");
        } else {
            DB::statement("ALTER TABLE products DROP CONSTRAINT IF EXISTS products_status_check");
            DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status IN ('in_stock', 'low', 'very_low', 'critical', 'out_of_stock'))");
        }

        // 4. Recalculate all statuses using the full new logic
        DB::statement("
            UPDATE products
            SET status = CASE
                WHEN (
                    quantity_on_hand - COALESCE((
                        SELECT SUM(ri.committed_qty)
                        FROM job_reservation_items ri
                        INNER JOIN job_reservations r ON ri.reservation_id = r.id
                        WHERE ri.product_id = products.id
                          AND r.status IN ('active', 'in_progress', 'on_hold')
                          AND r.deleted_at IS NULL
                    ), 0)
                ) < 0 AND COALESCE(reorder_point, 0) = 0
                    THEN 'out_of_stock'
                WHEN (
                    quantity_on_hand - COALESCE((
                        SELECT SUM(ri.committed_qty)
                        FROM job_reservation_items ri
                        INNER JOIN job_reservations r ON ri.reservation_id = r.id
                        WHERE ri.product_id = products.id
                          AND r.status IN ('active', 'in_progress', 'on_hold')
                          AND r.deleted_at IS NULL
                    ), 0)
                ) < 0
                    THEN 'critical'
                WHEN (
                    quantity_on_hand - COALESCE((
                        SELECT SUM(ri.committed_qty)
                        FROM job_reservation_items ri
                        INNER JOIN job_reservations r ON ri.reservation_id = r.id
                        WHERE ri.product_id = products.id
                          AND r.status IN ('active', 'in_progress', 'on_hold')
                          AND r.deleted_at IS NULL
                    ), 0)
                ) = 0
                    THEN 'out_of_stock'
                WHEN (
                    quantity_on_hand - COALESCE((
                        SELECT SUM(ri.committed_qty)
                        FROM job_reservation_items ri
                        INNER JOIN job_reservations r ON ri.reservation_id = r.id
                        WHERE ri.product_id = products.id
                          AND r.status IN ('active', 'in_progress', 'on_hold')
                          AND r.deleted_at IS NULL
                    ), 0)
                ) > COALESCE(reorder_point, 0)
                    THEN 'in_stock'
                WHEN (
                    quantity_on_hand - COALESCE((
                        SELECT SUM(ri.committed_qty)
                        FROM job_reservation_items ri
                        INNER JOIN job_reservations r ON ri.reservation_id = r.id
                        WHERE ri.product_id = products.id
                          AND r.status IN ('active', 'in_progress', 'on_hold')
                          AND r.deleted_at IS NULL
                    ), 0)
                ) > COALESCE(safety_stock, 0)
                    THEN 'low'
                ELSE 'very_low'
            END
            WHERE deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        DB::statement("UPDATE products SET status = 'low_stock' WHERE status IN ('low', 'very_low')");

        if ($driver === 'sqlite') {
            Schema::table('products', function (Blueprint $table) {
                $table->enum('status', ['in_stock', 'low_stock', 'critical', 'out_of_stock'])
                    ->default('in_stock')->change();
            });
        } else {
            DB::statement("ALTER TABLE products DROP CONSTRAINT IF EXISTS products_status_check");
            DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status IN ('in_stock', 'low_stock', 'critical', 'out_of_stock'))");
        }
    }
};
