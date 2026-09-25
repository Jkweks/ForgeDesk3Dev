<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * job_reservation_items.committed_qty/consumed_qty and products.quantity_committed
 * were made decimal(10,1) in 2026_07_08_000001 to support fractional (cut) part
 * quantities, but inventory_locations.quantity/quantity_committed and
 * products.quantity_on_hand were left as integer. Completing a reservation with a
 * fractional consumed quantity deducts that fraction from inventory_locations.quantity
 * and recalculates products.quantity_on_hand from it, which Postgres rejects with
 * "invalid input syntax for type integer" on those still-integer columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS inventory_commitments');

        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->decimal('quantity', 10, 1)->default(0)->change();
            $table->decimal('quantity_committed', 10, 1)->default(0)->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('quantity_on_hand', 10, 1)->default(0)->change();
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
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS inventory_commitments');

        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->integer('quantity')->default(0)->change();
            $table->integer('quantity_committed')->default(0)->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->integer('quantity_on_hand')->default(0)->change();
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
    }
};
