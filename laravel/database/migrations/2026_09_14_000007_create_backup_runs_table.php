<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per invocation of backup.sh (recorded via `php artisan backup:record`),
 * so the status page can show backup health without the app needing shell
 * access to the host cron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->date('run_date');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            // success | local_failed (a non-fatal local component failed) |
            // remote_failed (all local backups ok, offsite copy failed) | failed (fatal)
            $table->string('status');
            $table->json('components')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('run_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
