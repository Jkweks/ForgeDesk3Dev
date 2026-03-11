<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained('machines')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('frequency')->nullable();
            $table->string('assigned_to')->nullable();
            $table->integer('interval_count')->nullable();
            $table->enum('interval_unit', ['day', 'week', 'month', 'year'])->nullable();
            $table->date('start_date')->nullable();
            $table->enum('status', ['active', 'paused', 'retired'])->default('active');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->timestamps();
            $table->softDeletes();

            $table->index('machine_id');
            $table->index('status');
            $table->index('priority');
            $table->index('start_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_tasks');
    }
};
