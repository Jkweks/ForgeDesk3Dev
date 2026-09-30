<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the tiger-bridge connection details (previously TIGER_BRIDGE_URL /
 * TIGER_BRIDGE_TOKEN / TIGER_BRIDGE_FAKE env vars) and the cut-station tablet
 * IP allowlist (previously CUTFLOW_TABLET_ALLOWED_IPS) onto the settings
 * singleton, so an admin can change them from the app instead of editing
 * .env and restarting.
 */
return new class extends Migration
{
    protected $connection = 'cutflow';

    public function up(): void
    {
        Schema::connection('cutflow')->table('cutflow_settings', function (Blueprint $table) {
            $table->string('bridge_url')->nullable();
            $table->string('bridge_token')->nullable();
            $table->boolean('bridge_fake')->default(false);
            $table->text('tablet_allowed_ips')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('cutflow')->table('cutflow_settings', function (Blueprint $table) {
            $table->dropColumn(['bridge_url', 'bridge_token', 'bridge_fake', 'tablet_allowed_ips']);
        });
    }
};
