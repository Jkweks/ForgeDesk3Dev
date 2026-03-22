<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Recalculate all product statuses using the new 5-tier logic:
     *   in_stock  : available > reorder_point
     *   low       : safety_stock < available <= reorder_point
     *   very_low  : 0 < available <= safety_stock
     *   out_of_stock: available <= 0  (or overcommitted with reorder_point = 0)
     *   critical  : available < 0 (overcommitted) AND reorder_point > 0
     */
    public function up(): void
    {
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
        // No rollback — status is a derived value, not source-of-truth data
    }
};
