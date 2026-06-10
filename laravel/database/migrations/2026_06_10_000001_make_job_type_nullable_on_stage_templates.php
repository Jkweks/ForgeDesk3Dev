<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_stage_templates', function (Blueprint $table) {
            $table->string('job_type')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('fd_stage_templates', function (Blueprint $table) {
            $table->string('job_type')->nullable(false)->change();
        });
    }
};
