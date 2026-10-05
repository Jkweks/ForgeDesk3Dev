<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrects the formula model after checking fab_utils' actual live evaluator
 * (configurator/index.html): a "section:<Role>" term and "if_threshold" do NOT
 * reference another profile's per-config computed cut length — they reference
 * that profile's fixed section_height (a static catalog dimension, e.g. jamb
 * depth), looked up from ALL profiles in the series regardless of which ones
 * end up included in a given BOM. TH (total frame height), without a transom,
 * similarly resolves to H + the Door Head profile's section_height.
 *
 * threshold_deduct (added in 2026_09_18_000004) doesn't exist in the source
 * schema/evaluator and is replaced by section_height + qty_per_opening, which do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurator_frame_profiles', function (Blueprint $table) {
            $table->decimal('section_height', 8, 4)->default(0)->after('glass_thicknesses');
            $table->unsignedInteger('qty_per_opening')->default(1)->after('section_height');
        });

        Schema::table('configurator_frame_profiles', function (Blueprint $table) {
            $table->dropColumn('threshold_deduct');
        });
    }

    public function down(): void
    {
        Schema::table('configurator_frame_profiles', function (Blueprint $table) {
            $table->decimal('threshold_deduct', 8, 4)->default(0);
        });

        Schema::table('configurator_frame_profiles', function (Blueprint $table) {
            $table->dropColumn(['section_height', 'qty_per_opening']);
        });
    }
};
