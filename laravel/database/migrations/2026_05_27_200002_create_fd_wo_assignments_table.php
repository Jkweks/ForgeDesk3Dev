<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fd_wo_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('fd_work_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('fd_users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['work_order_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fd_wo_assignments');
    }
};
