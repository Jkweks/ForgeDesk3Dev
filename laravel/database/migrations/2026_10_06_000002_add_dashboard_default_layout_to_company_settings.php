<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-set default dashboard layout (same shape as users.dashboard_prefs),
 * used for users who haven't customized theirs and for "reset to default".
 * Null falls back to the built-in layout in App\Dashboard\WidgetRegistry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->json('dashboard_default_layout')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('dashboard_default_layout');
        });
    }
};
