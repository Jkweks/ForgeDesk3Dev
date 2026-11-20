<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// New work orders get a "Project Engineer Review" step. It is only required for job numbers that start with
// 1 or 4 (not dates); every other job gets it as not_required (see FdWorkOrder::boot / BusinessJob::requiresPeReview).
// Existing work orders are left alone: their checklists don't change.
return new class extends Migration
{
    public function up(): void
    {
        $name = 'Project Engineer Review';
        if (DB::table('fd_job_step_templates')->where('name', $name)->exists()) {
            return;
        }
        DB::table('fd_job_step_templates')->insert([
            'name' => $name, 'sort_order' => (int) DB::table('fd_job_step_templates')->max('sort_order') + 1,
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('fd_job_step_templates')->where('name', 'Project Engineer Review')->delete();
    }
};
