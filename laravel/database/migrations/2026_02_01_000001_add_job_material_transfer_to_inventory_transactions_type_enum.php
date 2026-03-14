<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // For PostgreSQL, we need to drop and recreate the check constraint
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // Drop the existing check constraint
            DB::statement("ALTER TABLE inventory_transactions DROP CONSTRAINT IF EXISTS inventory_transactions_type_check");

            // Add new check constraint with job_material_transfer included
            DB::statement("ALTER TABLE inventory_transactions ADD CONSTRAINT inventory_transactions_type_check CHECK (type::text = ANY (ARRAY['receipt'::character varying, 'shipment'::character varying, 'adjustment'::character varying, 'transfer'::character varying, 'return'::character varying, 'cycle_count'::character varying, 'job_issue'::character varying, 'issue'::character varying, 'job_material_transfer'::character varying]::text[]))");
        } elseif ($driver === 'mysql') {
            // MySQL syntax
            DB::statement("ALTER TABLE inventory_transactions MODIFY COLUMN type ENUM('receipt', 'shipment', 'adjustment', 'transfer', 'return', 'cycle_count', 'job_issue', 'issue', 'job_material_transfer')");
        }
        // SQLite doesn't enforce enum types, so no action needed
    }

    public function down(): void
    {
        // Remove 'job_material_transfer' from the enum
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // Drop the existing check constraint
            DB::statement("ALTER TABLE inventory_transactions DROP CONSTRAINT IF EXISTS inventory_transactions_type_check");

            // Add back the constraint without job_material_transfer
            DB::statement("ALTER TABLE inventory_transactions ADD CONSTRAINT inventory_transactions_type_check CHECK (type::text = ANY (ARRAY['receipt'::character varying, 'shipment'::character varying, 'adjustment'::character varying, 'transfer'::character varying, 'return'::character varying, 'cycle_count'::character varying, 'job_issue'::character varying, 'issue'::character varying]::text[]))");
        } elseif ($driver === 'mysql') {
            // MySQL syntax
            DB::statement("ALTER TABLE inventory_transactions MODIFY COLUMN type ENUM('receipt', 'shipment', 'adjustment', 'transfer', 'return', 'cycle_count', 'job_issue', 'issue')");
        }
        // SQLite doesn't enforce enum types, so no action needed
    }
};
