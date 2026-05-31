<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fd_work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('project_name');
            $table->string('job_number')->nullable();
            $table->string('wo_number');
            $table->string('job_type')->default('SF'); // SF | CW
            $table->string('system')->nullable();
            $table->date('date_received')->nullable();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_complete_date')->nullable();
            $table->date('requested_finish_date')->nullable();
            $table->unsignedInteger('joints')->default(0);
            $table->unsignedInteger('dr_fr_units')->default(0);
            $table->unsignedInteger('doors_units')->default(0);
            $table->decimal('estimated_hours_calc', 8, 2)->nullable(); // computed in PHP
            $table->decimal('estimated_hours_override', 8, 2)->nullable();
            $table->string('material_arrived')->nullable(); // Yes | SOF | null/date string
            $table->boolean('cut_list_glazer')->default(false);
            $table->text('notes')->nullable();
            $table->string('project_manager')->nullable();
            $table->foreignId('assigned_to_id')->nullable()->constrained('fd_users')->nullOnDelete();
            $table->boolean('archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index('archived');
            $table->index('planned_complete_date');
            $table->index('job_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fd_work_orders');
    }
};
