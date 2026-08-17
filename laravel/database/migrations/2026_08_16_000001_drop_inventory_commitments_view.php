<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // inventory_commitments was a raw SQL view that recomputed committed quantities
        // by summing job_reservation_items.committed_qty directly — it never accounted
        // for bins and diverged from the current bin-aware
        // JobReservationItem::binAwareCommitted() logic. Nothing in the app queries it
        // (grep confirms no references outside migrations), so it's dead and would return
        // wrong numbers if anyone ever did query it. Dropping it for good.
        DB::statement('DROP VIEW IF EXISTS inventory_commitments');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
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
