<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merged pairs: two configurations (one per tag, e.g. 1403 + 1409) folded into one opening. Each leaf keeps its
 * own door config row and its own door tag, marked 'active' / 'inactive'. Null everywhere else — an ordinary
 * single, or a pair imported under one tag, keeps the one-row-covers-both-leaves model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('door_frame_door_configs', function (Blueprint $table) {
            $table->string('leaf', 10)->nullable()->after('leaf_type');
        });
        Schema::table('door_frame_configuration_doors', function (Blueprint $table) {
            $table->string('leaf', 10)->nullable()->after('door_tag');
        });
    }

    public function down(): void
    {
        Schema::table('door_frame_door_configs', fn (Blueprint $t) => $t->dropColumn('leaf'));
        Schema::table('door_frame_configuration_doors', fn (Blueprint $t) => $t->dropColumn('leaf'));
    }
};
