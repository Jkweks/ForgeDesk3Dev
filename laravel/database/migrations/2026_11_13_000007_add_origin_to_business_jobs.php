<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a job was created: 'fabrication' (existing flow, unchanged) or 'pm' (Project Management bid).
 * Lets the adoption be tracked and field ownership tightened later; existing jobs are 'fabrication'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_jobs', function (Blueprint $table) {
            $table->string('origin', 20)->default('fabrication')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('business_jobs', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
