<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A job's site superintendent — same shape as the project manager: a synced
 * free-text label (`superintendent`) plus the real link (`superintendent_id`).
 * Completion emails for the job's work orders also go to this person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_jobs', function (Blueprint $table) {
            $table->string('superintendent')->nullable()->after('project_manager_id');
            $table->foreignId('superintendent_id')->nullable()->after('superintendent')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('business_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('superintendent_id');
            $table->dropColumn('superintendent');
        });
    }
};
