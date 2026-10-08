<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Job-level steps become admin-managed: fd_job_step_templates is the ordered list copied onto every new
 * work order (replacing the four names that were hard-coded in FdWorkOrder). Work orders also gain a
 * "pending" status — a WO is pending until all of its job steps are done, then active — so existing
 * active work orders with open steps are backfilled to pending.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fd_job_step_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        foreach (['Cut List Prepared', 'Cut List Reviewed', 'Dropbox Uploaded', 'Kanban Entered'] as $i => $name) {
            DB::table('fd_job_step_templates')->insert([
                'name' => $name, 'sort_order' => $i + 1, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('fd_work_orders')
            ->where('status', 'active')
            ->whereIn('id', DB::table('fd_job_steps')->whereNotIn('status', ['complete', 'not_required'])->select('work_order_id'))
            ->update(['status' => 'pending']);
    }

    public function down(): void
    {
        DB::table('fd_work_orders')->where('status', 'pending')->update(['status' => 'active']);
        Schema::dropIfExists('fd_job_step_templates');
    }
};
