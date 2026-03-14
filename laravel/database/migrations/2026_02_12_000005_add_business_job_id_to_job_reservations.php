<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add optional foreign key to business_jobs table for better integration.
     * Keep existing job_number/job_name fields for backward compatibility.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // For SQLite, we need to drop and recreate the view since it references job_reservations
        if ($driver === 'sqlite') {
            DB::statement('DROP VIEW IF EXISTS inventory_commitments');
        }

        Schema::table('job_reservations', function (Blueprint $table) {
            $table->foreignId('business_job_id')
                ->nullable()
                ->after('id')
                ->constrained('business_jobs')
                ->onDelete('restrict');

            $table->index('business_job_id');
        });

        // Recreate the view for SQLite
        if ($driver === 'sqlite') {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_reservations', function (Blueprint $table) {
            $table->dropForeign(['business_job_id']);
            $table->dropColumn('business_job_id');
        });
    }
};
