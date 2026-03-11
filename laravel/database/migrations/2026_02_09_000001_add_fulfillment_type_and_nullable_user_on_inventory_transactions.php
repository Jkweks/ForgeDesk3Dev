<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // Add 'fulfillment' to the type enum
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE inventory_transactions DROP CONSTRAINT IF EXISTS inventory_transactions_type_check");
            DB::statement("ALTER TABLE inventory_transactions ADD CONSTRAINT inventory_transactions_type_check CHECK (type::text = ANY (ARRAY['receipt'::character varying, 'shipment'::character varying, 'adjustment'::character varying, 'transfer'::character varying, 'return'::character varying, 'cycle_count'::character varying, 'job_issue'::character varying, 'issue'::character varying, 'job_material_transfer'::character varying, 'fulfillment'::character varying]::text[]))");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE inventory_transactions MODIFY COLUMN type ENUM('receipt', 'shipment', 'adjustment', 'transfer', 'return', 'cycle_count', 'job_issue', 'issue', 'job_material_transfer', 'fulfillment')");
        }
        // SQLite doesn't enforce enum types, so no action needed

        // Make user_id nullable for system-initiated transactions (job completions, repairs)
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE inventory_transactions DROP CONSTRAINT IF EXISTS inventory_transactions_type_check");
            DB::statement("ALTER TABLE inventory_transactions ADD CONSTRAINT inventory_transactions_type_check CHECK (type::text = ANY (ARRAY['receipt'::character varying, 'shipment'::character varying, 'adjustment'::character varying, 'transfer'::character varying, 'return'::character varying, 'cycle_count'::character varying, 'job_issue'::character varying, 'issue'::character varying, 'job_material_transfer'::character varying]::text[]))");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE inventory_transactions MODIFY COLUMN type ENUM('receipt', 'shipment', 'adjustment', 'transfer', 'return', 'cycle_count', 'job_issue', 'issue', 'job_material_transfer')");
        }
        // SQLite doesn't enforce enum types, so no action needed

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
