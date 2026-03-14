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
     * Add sequential reservation_id per job to job_reservations.
     * This allows each job to have its own sequence: Job ABC has res 1, 2, 3... Job DEF has res 1, 2, 3...
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // Drop the inventory_commitments view if it exists (for all database types)
        DB::statement('DROP VIEW IF EXISTS inventory_commitments');

        Schema::table('job_reservations', function (Blueprint $table) {
            // Add reservation_id column (sequential per job, starting at 1)
            $table->integer('reservation_id')->after('business_job_id')->nullable();

            // Add index for faster lookups
            $table->index(['business_job_id', 'reservation_id']);
        });

        // Populate reservation_id for existing records
        // Group by business_job_id and assign sequential numbers
        if ($driver === 'sqlite') {
            // SQLite approach: Use ROW_NUMBER equivalent via subquery
            DB::statement("
                UPDATE job_reservations
                SET reservation_id = (
                    SELECT COUNT(*) + 1
                    FROM job_reservations AS jr2
                    WHERE jr2.business_job_id = job_reservations.business_job_id
                    AND jr2.id < job_reservations.id
                )
                WHERE business_job_id IS NOT NULL
            ");

            // For records without business_job_id, set to 1 (orphaned records)
            DB::statement("
                UPDATE job_reservations
                SET reservation_id = 1
                WHERE business_job_id IS NULL
            ");
        } elseif ($driver === 'pgsql') {
            // PostgreSQL approach: Use ROW_NUMBER
            DB::statement("
                WITH numbered AS (
                    SELECT id,
                           ROW_NUMBER() OVER (PARTITION BY business_job_id ORDER BY id) as rn
                    FROM job_reservations
                    WHERE business_job_id IS NOT NULL
                )
                UPDATE job_reservations
                SET reservation_id = numbered.rn
                FROM numbered
                WHERE job_reservations.id = numbered.id
            ");

            // For records without business_job_id, set to 1 (orphaned records)
            DB::statement("
                UPDATE job_reservations
                SET reservation_id = 1
                WHERE business_job_id IS NULL
            ");
        } else {
            // MySQL approach: Use variables
            DB::statement("
                SET @rn := 0;
                SET @prev_job := NULL;

                UPDATE job_reservations
                JOIN (
                    SELECT
                        id,
                        @rn := IF(@prev_job = business_job_id, @rn + 1, 1) AS rn,
                        @prev_job := business_job_id AS prev_job
                    FROM job_reservations
                    WHERE business_job_id IS NOT NULL
                    ORDER BY business_job_id, id
                ) AS numbered ON job_reservations.id = numbered.id
                SET job_reservations.reservation_id = numbered.rn
            ");

            // For records without business_job_id, set to 1 (orphaned records)
            DB::statement("
                UPDATE job_reservations
                SET reservation_id = 1
                WHERE business_job_id IS NULL
            ");
        }

        // Now make it required and add unique constraint
        Schema::table('job_reservations', function (Blueprint $table) {
            // Change to not nullable (all records should have a value now)
            $table->integer('reservation_id')->nullable(false)->change();

            // Add unique constraint: each job can only have one reservation with a given ID
            $table->unique(['business_job_id', 'reservation_id'], 'unique_job_reservation_id');
        });

        // Recreate the inventory_commitments view
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_reservations', function (Blueprint $table) {
            $table->dropUnique('unique_job_reservation_id');
            $table->dropIndex(['business_job_id', 'reservation_id']);
            $table->dropColumn('reservation_id');
        });
    }
};
