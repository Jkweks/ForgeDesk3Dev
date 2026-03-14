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
        // Step 1: Create a placeholder storage location for items without one
        $placeholderId = DB::table('storage_locations')->insertGetId([
            'name' => 'Unassigned',
            'code' => 'UNASSIGNED',
            'type' => 'other',
            'description' => 'Placeholder location for items migrated from legacy string-based locations',
            'parent_id' => null,
            'path' => null,
            'depth' => 0,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Step 2: Update all inventory_locations without storage_location_id to use placeholder
        // This preserves all quantities without disruption
        DB::statement("
            UPDATE inventory_locations
            SET storage_location_id = ?
            WHERE storage_location_id IS NULL
        ", [$placeholderId]);

        // Step 3: Make storage_location_id NOT NULL (required)
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->foreignId('storage_location_id')->nullable(false)->change();
        });

        // Step 4: Drop the unique constraint on (product_id, location) before removing location field
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'location']);
            $table->dropIndex(['location']);
        });

        // Step 5: Remove the legacy location string field
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add the location string field
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->string('location')->after('product_id')->nullable();
            $table->index('location');
        });

        // Restore location strings from storage_location names
        DB::statement("
            UPDATE inventory_locations il
            SET location = (
                SELECT name FROM storage_locations sl
                WHERE sl.id = il.storage_location_id
            )
        ");

        // Re-add the unique constraint
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->unique(['product_id', 'location']);
        });

        // Make storage_location_id nullable again
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->foreignId('storage_location_id')->nullable()->change();
        });

        // Remove the placeholder location (optional, might have data by now)
        DB::table('storage_locations')
            ->where('code', 'UNASSIGNED')
            ->delete();
    }
};
