<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_wo_elevations', function (Blueprint $table) {
            $table->string('scope')->default('assemble')->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('fd_wo_elevations', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
