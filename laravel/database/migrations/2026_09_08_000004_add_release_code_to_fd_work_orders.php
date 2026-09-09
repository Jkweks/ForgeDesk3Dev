<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional custom release identifier for a work order. When set it replaces the
 * auto "R{release_number}" token in the release label; the sequential
 * release_number is still assigned and used as the fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_work_orders', function (Blueprint $table) {
            $table->string('release_code', 50)->nullable()->after('release_number');
        });
    }

    public function down(): void
    {
        Schema::table('fd_work_orders', fn (Blueprint $table) => $table->dropColumn('release_code'));
    }
};
