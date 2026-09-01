<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `priority` becomes an auto-derived rank (by due date). `due_date` is the input;
 * `priority_locked` pins a work order at its current rank so the auto-sequencer
 * leaves it alone (set when a manager hand-drags the queue).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_work_orders', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('date_issued');
            $table->boolean('priority_locked')->default(false)->after('priority');
        });

        // Existing rows are all unlocked; leave `priority` as-is until the first
        // resequence (triggered by the next due-date edit or the nightly job).
    }

    public function down(): void
    {
        Schema::table('fd_work_orders', function (Blueprint $table) {
            $table->dropColumn(['due_date', 'priority_locked']);
        });
    }
};
