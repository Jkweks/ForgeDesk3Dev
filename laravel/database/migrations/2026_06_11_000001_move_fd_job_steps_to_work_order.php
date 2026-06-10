<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_job_steps', function (Blueprint $table) {
            $table->dropForeign(['business_job_id']);
            $table->dropColumn('business_job_id');
            $table->foreignId('work_order_id')->after('id')->constrained('fd_work_orders')->cascadeOnDelete();
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
