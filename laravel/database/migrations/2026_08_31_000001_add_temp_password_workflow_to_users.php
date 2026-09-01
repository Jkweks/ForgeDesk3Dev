<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the "temporary password" onboarding workflow columns.
     *
     * When an admin creates a user, ForgeDesk now issues a random temporary
     * password (emailed to the user) instead of the admin choosing one. The
     * user must change it within `config('auth.temp_password.ttl_hours')` hours
     * or the account is locked out until an admin resends the invitation.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('is_active');
            // When the current password was set. For a temporary password this is
            // the moment the invitation was issued; the 48h clock counts from here.
            $table->timestamp('password_set_at')->nullable()->after('must_change_password');
        });

        // Existing accounts already have working passwords — leave them alone.
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['must_change_password', 'password_set_at']);
        });
    }
};
