<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Project Management to-do list (feature #28): job-specific or general, assignable. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_todos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_job_id')->nullable()->constrained('business_jobs')->cascadeOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['assigned_to', 'completed_at']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_todos');
    }
};
