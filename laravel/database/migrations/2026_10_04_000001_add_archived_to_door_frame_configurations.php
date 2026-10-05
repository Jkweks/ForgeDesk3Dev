<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A completed work order archives its door/frame configurations: they drop out of the configurator,
 * label and package lists but stay on file (unlike soft-deleting). Re-opening the work order restores
 * them. Backfills configurations already tied to completed work orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('door_frame_configurations', function (Blueprint $table) {
            $table->boolean('archived')->default(false)->after('status');
            $table->timestamp('archived_at')->nullable()->after('archived');
            $table->index('archived');
        });

        DB::table('door_frame_configurations')
            ->whereIn('work_order_id', DB::table('fd_work_orders')->where('status', 'complete')->select('id'))
            ->update(['archived' => true, 'archived_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('door_frame_configurations', function (Blueprint $table) {
            $table->dropIndex(['archived']);
            $table->dropColumn(['archived', 'archived_at']);
        });
    }
};
