<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quarter-day (2h of an 8h day) resolution for the Work Queue schedule: which quarter (0-3) of the
 * start day work begins in and which quarter of the end day it finishes in. Defaults are the whole day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_work_orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('planned_start_quarter')->default(0);
            $table->unsignedSmallInteger('planned_completion_quarter')->default(3);
        });
        Schema::table('fd_schedule_entries', function (Blueprint $table) {
            $table->unsignedSmallInteger('start_quarter')->default(0);
            $table->unsignedSmallInteger('end_quarter')->default(3);
        });
    }

    public function down(): void
    {
        Schema::table('fd_work_orders', fn (Blueprint $t) => $t->dropColumn(['planned_start_quarter', 'planned_completion_quarter']));
        Schema::table('fd_schedule_entries', fn (Blueprint $t) => $t->dropColumn(['start_quarter', 'end_quarter']));
    }
};
