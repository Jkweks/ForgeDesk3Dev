<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Each released elevation remembers the Fabrication elevation it was seeded as, so approval seeds once and only once.
 * - Fabrication can edit a pending request; each edit is kept (who, what, why).
 * - system_notifications can be addressed to one person, so the PM who made a request hears about changes to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_request_elevations', function (Blueprint $table) {
            $table->foreignId('fd_wo_elevation_id')->nullable()->after('drawing_elevation_id')->constrained('fd_wo_elevations')->nullOnDelete();
        });

        Schema::create('work_order_request_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('summary');                    // what changed, one line each
            $table->text('note')->nullable();           // why, in the editor's words
            $table->timestamps();
            $table->index('work_order_request_id');
        });

        Schema::table('system_notifications', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();   // null = the shared feed
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('system_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::dropIfExists('work_order_request_changes');
        Schema::table('work_order_request_elevations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fd_wo_elevation_id');
        });
    }
};
