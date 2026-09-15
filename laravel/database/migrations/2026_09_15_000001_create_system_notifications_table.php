<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-facing in-app alerts shown via the nav bar bell (e.g. "backups have
 * been failing for 3 days"). Named distinctly from Laravel's own
 * database-channel notifications table (which this app doesn't use) since
 * the shape and purpose are different — these are system/ops alerts, not
 * per-notifiable mail/db notifications.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // e.g. 'backup_failing'
            $table->string('level')->default('warning'); // info | warning | danger
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->foreignId('dismissed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'dismissed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_notifications');
    }
};
