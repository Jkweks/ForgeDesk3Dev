<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-step gating: a stage/template can declare that it blocks the next stage
 * until it reaches a terminal status. Default `true` reproduces the (previously
 * implicit) "do them in order" expectation without any backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_stage_templates', function (Blueprint $table) {
            $table->boolean('blocks_next')->default(true)->after('sort_order');
        });

        Schema::table('fd_wo_stages', function (Blueprint $table) {
            $table->boolean('blocks_next')->default(true)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('fd_stage_templates', function (Blueprint $table) {
            $table->dropColumn('blocks_next');
        });

        Schema::table('fd_wo_stages', function (Blueprint $table) {
            $table->dropColumn('blocks_next');
        });
    }
};
