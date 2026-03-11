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
        Schema::table('inventory_locations', function (Blueprint $table) {
            // Add foreign key to storage_locations
            $table->foreignId('storage_location_id')->nullable()->after('product_id')->constrained('storage_locations')->onDelete('set null');
            $table->index('storage_location_id');
        });

        // Optionally sync existing data: match inventory_locations.location strings to storage_locations.name
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite doesn't support table aliases in UPDATE
            DB::statement("
                UPDATE inventory_locations
                SET storage_location_id = (
                    SELECT id FROM storage_locations
                    WHERE storage_locations.name = inventory_locations.location
                    LIMIT 1
                )
                WHERE EXISTS (
                    SELECT 1 FROM storage_locations WHERE storage_locations.name = inventory_locations.location
                )
            ");
        } else {
            // PostgreSQL/MySQL syntax with aliases
            DB::statement("
                UPDATE inventory_locations il
                SET storage_location_id = (
                    SELECT id FROM storage_locations sl
                    WHERE sl.name = il.location
                    LIMIT 1
                )
                WHERE EXISTS (
                    SELECT 1 FROM storage_locations sl WHERE sl.name = il.location
                )
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->dropForeign(['storage_location_id']);
            $table->dropColumn('storage_location_id');
        });
    }
};
