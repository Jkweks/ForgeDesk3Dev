<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_job_steps', function (Blueprint $table) {
            $table->dropForeign(['business_job_id']);
            $table->dropColumn('business_job_id');
            // Add nullable first so existing rows don't violate NOT NULL
            $table->unsignedBigInteger('work_order_id')->nullable()->after('id');
        });

        // Existing rows are orphaned (tied to jobs, not work orders) — delete them
        DB::table('fd_job_steps')->whereNull('work_order_id')->delete();

        // Now enforce NOT NULL and add the foreign key
        Schema::table('fd_job_steps', function (Blueprint $table) {
            $table->unsignedBigInteger('work_order_id')->nullable(false)->change();
            $table->foreign('work_order_id')->references('id')->on('fd_work_orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fd_job_steps', function (Blueprint $table) {
            $table->dropForeign(['work_order_id']);
            $table->dropColumn('work_order_id');
            $table->foreignId('business_job_id')->after('id')->constrained('business_jobs')->cascadeOnDelete();
        });
    }
};
