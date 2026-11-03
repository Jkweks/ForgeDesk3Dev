<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_wo_stages', function (Blueprint $table) {
            // Manual position in the Programming Queue (drag and drop). Null = not ordered by hand yet,
            // so the line falls back to the default ranking (status, work-order priority, requested date).
            $table->unsignedInteger('programming_rank')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('fd_wo_stages', function (Blueprint $table) {
            $table->dropColumn('programming_rank');
        });
    }
};
