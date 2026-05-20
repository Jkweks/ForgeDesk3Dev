<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_jobs', function (Blueprint $table) {
            $table->string('project_manager')->nullable()->after('customer_name');
        });
    }

    public function down(): void
    {
        Schema::table('business_jobs', function (Blueprint $table) {
            $table->dropColumn('project_manager');
        });
    }
};
