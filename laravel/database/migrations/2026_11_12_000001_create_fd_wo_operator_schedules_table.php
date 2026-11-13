<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-operator planning windows for a work order on the Work Queue schedule.
 * A work order shared by several operators used to have one set of planned
 * dates, so moving it on one operator's row moved it on all of them. Each
 * operator now gets their own window; the work order's planned dates become
 * the envelope (earliest start → latest finish) of those windows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fd_wo_operator_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('fd_work_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('fd_users')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('start_quarter')->default(0);
            $table->unsignedSmallInteger('end_quarter')->default(3);
            $table->timestamps();

            $table->unique(['work_order_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fd_wo_operator_schedules');
    }
};
