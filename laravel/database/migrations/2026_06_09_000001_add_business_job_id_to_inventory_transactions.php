<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('business_job_id')->nullable()->after('reference_id');
            $table->foreign('business_job_id')->references('id')->on('business_jobs')->nullOnDelete();
            $table->index('business_job_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropForeign(['business_job_id']);
            $table->dropIndex(['business_job_id']);
            $table->dropColumn('business_job_id');
        });
    }
};
