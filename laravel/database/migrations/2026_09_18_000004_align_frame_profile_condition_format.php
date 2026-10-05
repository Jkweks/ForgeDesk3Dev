<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Realigns configurator_frame_profiles with the actual fab_utils schema so a
 * catalog import (and future re-syncs) can be applied without lossy translation:
 *  - condition: a single nullable string (single/pair/transom/threshold/transom_pair)
 *    replaces the four separate applies_to_* booleans.
 *  - glass_thicknesses: exact allowed transom glazing values (JSON array) replaces
 *    the glass_min/glass_max numeric range.
 *  - threshold_deduct: additional length subtracted when a threshold is present,
 *    referenced by an "if_threshold" formula term.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurator_frame_profiles', function (Blueprint $table) {
            $table->string('condition', 20)->nullable()->after('product_id');
            $table->json('glass_thicknesses')->nullable()->after('condition');
            $table->decimal('threshold_deduct', 8, 4)->default(0)->after('glass_thicknesses');
        });

        Schema::table('configurator_frame_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'applies_to_single', 'applies_to_pair', 'applies_to_transom', 'applies_to_threshold',
                'glass_min', 'glass_max',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('configurator_frame_profiles', function (Blueprint $table) {
            $table->boolean('applies_to_single')->default(true);
            $table->boolean('applies_to_pair')->default(true);
            $table->boolean('applies_to_transom')->default(false);
            $table->boolean('applies_to_threshold')->default(false);
            $table->decimal('glass_min', 8, 3)->nullable();
            $table->decimal('glass_max', 8, 3)->nullable();
        });

        Schema::table('configurator_frame_profiles', function (Blueprint $table) {
            $table->dropColumn(['condition', 'glass_thicknesses', 'threshold_deduct']);
        });
    }
};
