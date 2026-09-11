<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step grouping for the sequential gate. Steps that share a `phase` run
 * concurrently — they do not gate each other — while a step is still blocked by
 * any `blocks_next` step in an *earlier* phase that has not reached a terminal
 * status.
 *
 * `phase` is nullable; when null the gate falls back to `sort_order`, so every
 * existing template and work order keeps its current strictly-linear behaviour
 * with no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_stage_templates', function (Blueprint $table) {
            $table->unsignedInteger('phase')->nullable()->after('sort_order');
        });

        Schema::table('fd_wo_stages', function (Blueprint $table) {
            $table->unsignedInteger('phase')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('fd_stage_templates', function (Blueprint $table) {
            $table->dropColumn('phase');
        });

        Schema::table('fd_wo_stages', function (Blueprint $table) {
            $table->dropColumn('phase');
        });
    }
};
