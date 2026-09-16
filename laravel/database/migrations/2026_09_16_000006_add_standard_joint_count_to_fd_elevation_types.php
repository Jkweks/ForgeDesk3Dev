<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A per-type "standard joints" default (e.g. a door is 6 joints, a frame is
 * 3) used to auto-fill a new elevation's joint_qty as quantity x this value.
 * It's a starting point, not a lock — joint_qty stays freely editable per
 * elevation afterward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_elevation_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('standard_joint_count')->nullable()->after('color');
        });

        DB::table('fd_elevation_types')->where('name', 'Door')->update(['standard_joint_count' => 6]);
        DB::table('fd_elevation_types')->where('name', 'Frame')->update(['standard_joint_count' => 3]);
    }

    public function down(): void
    {
        Schema::table('fd_elevation_types', function (Blueprint $table) {
            $table->dropColumn('standard_joint_count');
        });
    }
};
