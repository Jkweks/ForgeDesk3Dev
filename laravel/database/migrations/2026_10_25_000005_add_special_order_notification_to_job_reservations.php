<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional "tell these people when every special-order item on this reservation is in".
 * One-shot: special_received_notified_at records that the notice went out (re-armed if a
 * special-order item is added afterwards).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_reservations', function (Blueprint $table) {
            $table->boolean('notify_special_received')->default(false);
            $table->timestamp('special_received_notified_at')->nullable();
        });

        Schema::create('job_reservation_notify_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_reservation_id')->constrained('job_reservations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['job_reservation_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_reservation_notify_users');
        Schema::table('job_reservations', function (Blueprint $table) {
            $table->dropColumn(['notify_special_received', 'special_received_notified_at']);
        });
    }
};
