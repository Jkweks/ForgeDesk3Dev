<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allow quantity_committed to store fractional stick values (e.g. 1.3 sticks)
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('quantity_committed', 10, 4)->default(0)->change();
        });

        // Update the view: replace CEIL(SUM)::integer (rounds to whole number)
        // with CEIL(SUM * 10) / 10 (rounds up to nearest tenth).
        // This way 0.8 + 0.3 + 0.2 = 1.3, not 2.
        DB::statement('DROP VIEW IF EXISTS inventory_commitments');
        DB::statement("
            CREATE VIEW inventory_commitments AS
            SELECT
                p.id AS product_id,
                p.sku,
                p.part_number,
                p.finish,
                p.description,
                p.quantity_on_hand AS stock,
                CEIL(COALESCE(SUM(
                    CASE
                        WHEN r.status IN ('active', 'in_progress', 'on_hold')
                        THEN ri.committed_qty
                        ELSE 0
                    END
                ), 0) * 10) / 10.0 AS committed_qty,
                p.quantity_on_hand - CEIL(COALESCE(SUM(
                    CASE
                        WHEN r.status IN ('active', 'in_progress', 'on_hold')
                        THEN ri.committed_qty
                        ELSE 0
                    END
                ), 0) * 10) / 10.0 AS available_qty
            FROM products p
            LEFT JOIN job_reservation_items ri ON p.id = ri.product_id
            LEFT JOIN job_reservations r ON ri.reservation_id = r.id AND r.deleted_at IS NULL
            GROUP BY p.id
        ");

        // Recalculate stored quantity_committed for all products using the new formula
        DB::statement("
            UPDATE products SET quantity_committed = (
                SELECT CEIL(COALESCE(SUM(ri.committed_qty), 0) * 10) / 10.0
                FROM job_reservation_items ri
                JOIN job_reservations r ON ri.reservation_id = r.id
                WHERE ri.product_id = products.id
                  AND r.status IN ('active', 'in_progress', 'on_hold')
                  AND r.deleted_at IS NULL
            )
            WHERE id IN (
                SELECT DISTINCT ri.product_id FROM job_reservation_items ri
                JOIN job_reservations r ON ri.reservation_id = r.id
                WHERE r.status IN ('active', 'in_progress', 'on_hold')
                  AND r.deleted_at IS NULL
            )
        ");
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('quantity_committed')->default(0)->change();
        });

        DB::statement('DROP VIEW IF EXISTS inventory_commitments');
        DB::statement("
            CREATE VIEW inventory_commitments AS
            SELECT
                p.id AS product_id,
                p.sku,
                p.part_number,
                p.finish,
                p.description,
                p.quantity_on_hand AS stock,
                CEIL(COALESCE(SUM(
                    CASE
                        WHEN r.status IN ('active', 'in_progress', 'on_hold')
                        THEN ri.committed_qty
                        ELSE 0
                    END
                ), 0) * 10) / 10.0 AS committed_qty,
                p.quantity_on_hand - CEIL(COALESCE(SUM(
                    CASE
                        WHEN r.status IN ('active', 'in_progress', 'on_hold')
                        THEN ri.committed_qty
                        ELSE 0
                    END
                ), 0) * 10) / 10.0 AS available_qty
            FROM products p
            LEFT JOIN job_reservation_items ri ON p.id = ri.product_id
            LEFT JOIN job_reservations r ON ri.reservation_id = r.id AND r.deleted_at IS NULL
            GROUP BY p.id
        ");
    }
};
