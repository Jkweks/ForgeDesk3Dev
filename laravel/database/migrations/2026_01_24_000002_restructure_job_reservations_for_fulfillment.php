<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop old job_reservations table (if you want to preserve data, export first)
        Schema::dropIfExists('job_reservations');

        // Create new job_reservations table (header/metadata)
        Schema::create('job_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('job_number', 100);
            $table->integer('release_number');
            $table->string('job_name', 255);
            $table->string('requested_by', 255);
            $table->date('needed_by')->nullable();
            $table->enum('status', ['draft', 'active', 'in_progress', 'fulfilled', 'on_hold', 'cancelled'])
                ->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['job_number', 'release_number'], 'unique_job_release');
            $table->index('status');
            $table->index('needed_by');
        });

        // Create job_reservation_items table (line items)
        Schema::create('job_reservation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('job_reservations')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products');
            $table->integer('requested_qty');
            $table->integer('committed_qty');
            $table->integer('consumed_qty')->default(0);
            $table->timestamps();

            // Indexes
            $table->unique(['reservation_id', 'product_id'], 'unique_reservation_product');
            $table->index('reservation_id');
            $table->index('product_id');
        });

        // Create inventory_commitments view
        // This calculates real-time committed quantities and available inventory
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
        // Drop view first
        DB::statement('DROP VIEW IF EXISTS inventory_commitments');

        // Drop tables (foreign keys will cascade)
        Schema::dropIfExists('job_reservation_items');
        Schema::dropIfExists('job_reservations');

        // Restore old job_reservations structure if needed
        // (You would need to implement this based on your backup)
    }
};
