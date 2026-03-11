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
        // Only run for PostgreSQL databases
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Get all tables
        $tables = [
            'users',
            'categories',
            'products',
            'suppliers',
            'orders',
            'order_items',
            'inventory_transactions',
            'inventory_locations',
            'job_reservations',
            'job_reservation_items',
            'purchase_orders',
            'purchase_order_items',
            'cycle_count_sessions',
            'cycle_count_items',
            'machines',
            'assets',
            'maintenance_tasks',
            'maintenance_records',
            'machine_tooling',
            'storage_locations',
            'required_parts',
        ];

        foreach ($tables as $table) {
            // Check if table exists
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            // Check if table has an id column
            if (!DB::getSchemaBuilder()->hasColumn($table, 'id')) {
                continue;
            }

            // Reset the sequence for this table
            $sequenceName = $table . '_id_seq';

            // Get the current max ID
            $maxId = DB::table($table)->max('id') ?? 0;

            // Set the sequence to max ID + 1
            $nextId = $maxId + 1;

            try {
                DB::statement("SELECT setval('{$sequenceName}', {$nextId}, false)");
                echo "Fixed sequence for {$table}: set to {$nextId}\n";
            } catch (\Exception $e) {
                // Sequence might not exist or table might not use sequences
                echo "Skipped {$table}: {$e->getMessage()}\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration cannot be reversed
    }
};
