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
        Schema::table('cycle_count_sessions', function (Blueprint $table) {
            $table->json('storage_location_ids')->nullable()->after('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cycle_count_sessions', function (Blueprint $table) {
            $table->dropColumn('storage_location_ids');
        });
    }
};
