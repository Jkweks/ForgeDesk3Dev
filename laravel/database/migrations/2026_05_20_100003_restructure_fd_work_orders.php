<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop foreign key on assigned_to_id before dropping column
        Schema::table('fd_work_orders', function (Blueprint $table) {
            // job_type and planned_complete_date have indexes from the create
            // migration; drop them first so the column drop below doesn't leave
            // SQLite's table rebuild referencing a now-missing column via an index.
            $table->dropIndex(['job_type']);
            $table->dropIndex(['planned_complete_date']);

            // Drop old columns no longer needed
            $table->dropColumn([
                'project_name', 'job_number', 'wo_number', 'job_type', 'system',
                'date_received', 'planned_start_date', 'planned_complete_date',
                'requested_finish_date', 'joints', 'dr_fr_units', 'doors_units',
                'estimated_hours_calc', 'estimated_hours_override',
                'cut_list_glazer', 'project_manager', 'archived_at',
            ]);
        });

        // Add new columns and drop the FK separately (some DBs need separate statements)
        Schema::table('fd_work_orders', function (Blueprint $table) {
            $table->foreignId('business_job_id')
                ->nullable()
                ->after('id')
                ->constrained('business_jobs')
                ->nullOnDelete();
            $table->unsignedInteger('release_number')->default(1)->after('business_job_id');
            $table->date('date_issued')->nullable()->after('release_number');
            $table->string('material_delivery')->nullable()->after('date_issued'); // date | 'In Shop' | 'SOF'

            $table->index(['business_job_id', 'release_number']);
        });

        // Drop the old assigned_to_id FK column separately
        Schema::table('fd_work_orders', function (Blueprint $table) {
            $table->dropForeign(['assigned_to_id']);
            $table->dropColumn('assigned_to_id');
        });
    }

    public function down(): void
    {
        Schema::table('fd_work_orders', function (Blueprint $table) {
            $table->dropForeign(['business_job_id']);
            $table->dropColumn(['business_job_id', 'release_number', 'date_issued', 'material_delivery']);

            // Restore removed columns
            $table->string('project_name')->after('id');
            $table->string('job_number')->nullable();
            $table->string('wo_number');
            $table->string('job_type')->default('SF');
            $table->string('system')->nullable();
            $table->date('date_received')->nullable();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_complete_date')->nullable();
            $table->date('requested_finish_date')->nullable();
            $table->unsignedInteger('joints')->default(0);
            $table->unsignedInteger('dr_fr_units')->default(0);
            $table->unsignedInteger('doors_units')->default(0);
            $table->decimal('estimated_hours_calc', 8, 2)->nullable();
            $table->decimal('estimated_hours_override', 8, 2)->nullable();
            $table->boolean('cut_list_glazer')->default(false);
            $table->string('project_manager')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('assigned_to_id')->nullable()->constrained('fd_users')->nullOnDelete();
        });
    }
};
