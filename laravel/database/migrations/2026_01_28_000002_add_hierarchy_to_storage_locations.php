<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('storage_locations', function (Blueprint $table) {
            // Add parent_id for hierarchical structure
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('storage_locations')->onDelete('cascade');

            // Add path for efficient hierarchy queries (stores full path like "1/3/7")
            $table->string('path')->nullable()->after('parent_id');

            // Add depth for easy filtering by level
            $table->integer('depth')->default(0)->after('path');

            // Update type field to support hierarchy
            // aisle -> rack -> shelf -> bin
            $table->string('type')->default('bin')->change();

            $table->index('parent_id');
            $table->index('path');
            $table->index('depth');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('storage_locations', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'path', 'depth']);
        });
    }
};
