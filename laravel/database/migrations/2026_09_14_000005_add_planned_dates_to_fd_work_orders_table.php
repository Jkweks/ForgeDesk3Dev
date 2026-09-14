<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_work_orders', function (Blueprint $table) {
            $table->date('planned_start_date')->nullable()->after('due_date');
            $table->date('planned_completion_date')->nullable()->after('planned_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('fd_work_orders', function (Blueprint $table) {
            $table->dropColumn(['planned_start_date', 'planned_completion_date']);
        });
    }
};
