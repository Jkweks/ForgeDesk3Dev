<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('cutflow')->table('stick_sessions', function (Blueprint $table) {
            $table->json('drop_plan')->nullable()->after('waste_inches');
        });
    }

    public function down(): void
    {
        Schema::connection('cutflow')->table('stick_sessions', function (Blueprint $table) {
            $table->dropColumn('drop_plan');
        });
    }
};
