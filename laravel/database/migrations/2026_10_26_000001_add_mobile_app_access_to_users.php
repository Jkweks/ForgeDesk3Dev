<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an admin allow a ForgeDesk user to sign in to the separate field (mobile) app. Only users
 * with this flag are published to the field app's database (see FieldSync::publishUsers()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('mobile_app_access')->default(false)->after('is_disabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mobile_app_access');
        });
    }
};
