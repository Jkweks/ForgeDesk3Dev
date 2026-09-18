<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give work orders a first-class lifecycle status.
 *
 * Until now WO completion was a purely computed roll-up of stage/elevation
 * state (FdWorkOrder::isComplete()) with nothing persisted. This adds a stored
 * `status` (active | on_hold | complete) that the office drives manually for
 * holds and confirms on completion, plus the bookkeeping for the "email the PM
 * on completion" prompt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_work_orders', function (Blueprint $table) {
            $table->string('status')->default('active')->after('material_delivery'); // active | on_hold | complete
            $table->timestamp('completed_at')->nullable()->after('status');
            $table->foreignId('completed_by_user_id')->nullable()->after('completed_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('completion_email_sent_at')->nullable()->after('completed_by_user_id');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('fd_work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by_user_id');
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'completed_at', 'completion_email_sent_at']);
        });
    }
};
