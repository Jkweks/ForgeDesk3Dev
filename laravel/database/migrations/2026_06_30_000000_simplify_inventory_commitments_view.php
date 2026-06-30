<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Bin-packing cannot be expressed in SQL, so the view uses SUM(committed_qty)
        // as a raw total. The authoritative bin-packed committed value lives in
        // products.quantity_committed (maintained by JobReservationItem::syncProductCommittedQuantity).
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
                p.quantity_committed AS committed_qty,
                p.quantity_on_hand - p.quantity_committed AS available_qty
            FROM products p
        ");
    }

    public function down(): void
    {
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
