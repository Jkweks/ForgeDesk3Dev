<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Work Queue schedule entries that sit on one operator's row: a team-member
 * assist on a work order (own date span, independent of the work order's
 * planned dates) or a free-text note (day off, out early, ...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fd_schedule_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('fd_users')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('fd_work_orders')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fd_schedule_entries');
    }
};
