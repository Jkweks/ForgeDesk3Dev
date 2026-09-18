<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for work-order status changes — mirrors fd_stage_log. Every
 * hold / release / completion writes one row here, carrying the office user's
 * note so the reason for a hold (or a completion) stays visible on the panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fd_wo_status_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('fd_work_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('work_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fd_wo_status_log');
    }
};
