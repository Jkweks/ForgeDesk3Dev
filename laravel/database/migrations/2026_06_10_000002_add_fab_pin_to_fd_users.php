<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_users', function (Blueprint $table) {
            $table->string('fab_pin')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('fd_users', function (Blueprint $table) {
            $table->dropColumn('fab_pin');
        });
    }
};
