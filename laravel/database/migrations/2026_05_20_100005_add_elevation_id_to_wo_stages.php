<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_wo_stages', function (Blueprint $table) {
            $table->foreignId('elevation_id')
                ->nullable()
                ->after('work_order_id')
                ->constrained('fd_wo_elevations')
                ->cascadeOnDelete();

            // Make work_order_id nullable — stages now attach to elevations
            $table->foreignId('work_order_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('fd_wo_stages', function (Blueprint $table) {
            $table->dropForeign(['elevation_id']);
            $table->dropColumn('elevation_id');
            $table->foreignId('work_order_id')->nullable(false)->change();
        });
    }
};
