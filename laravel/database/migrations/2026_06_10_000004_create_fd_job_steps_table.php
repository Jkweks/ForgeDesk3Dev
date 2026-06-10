<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fd_job_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_job_id')->constrained('business_jobs')->cascadeOnDelete();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->string('status')->default('pending'); // pending, complete, not_required
            $table->unsignedBigInteger('completed_by_id')->nullable();
            $table->foreign('completed_by_id')->references('id')->on('fd_users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fd_job_steps');
    }
};
