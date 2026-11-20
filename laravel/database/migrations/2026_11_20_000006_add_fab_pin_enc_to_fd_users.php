<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Admin-viewable copy of a fab user's shop-floor PIN (encrypted with the app key). The bcrypt hash in
// `fab_pin` is still what sign-in checks; this column only exists so an admin can look a PIN up.
// PINs set before this column existed are hash-only and cannot be recovered: set them again.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_users', function (Blueprint $table) {
            $table->text('fab_pin_enc')->nullable()->after('fab_pin');
        });
    }

    public function down(): void
    {
        Schema::table('fd_users', function (Blueprint $table) {
            $table->dropColumn('fab_pin_enc');
        });
    }
};
