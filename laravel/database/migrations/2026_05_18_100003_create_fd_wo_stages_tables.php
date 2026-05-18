<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fd_wo_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('fd_work_orders')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('fd_stage_templates')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('pending'); // pending | in_progress | complete | blocked
            $table->foreignId('assigned_to_id')->nullable()->constrained('fd_users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('work_order_id');
            $table->index('status');
        });

        Schema::create('fd_stage_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('fd_wo_stages')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('fd_users')->nullOnDelete();
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fd_stage_log');
        Schema::dropIfExists('fd_wo_stages');
    }
};
