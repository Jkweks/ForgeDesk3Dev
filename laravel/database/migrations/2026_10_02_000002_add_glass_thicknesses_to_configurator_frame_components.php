<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * fab_utils' frame calculator filters each profile accessory by glass
 * thickness (e.g. the 1" storefront gasket only applies to 1" glazing); the
 * import dropped that filter, so gaskets were added to every glazing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurator_frame_components', function ($table) {
            $table->json('glass_thicknesses')->nullable()->after('qty_per');
        });
    }

    public function down(): void
    {
        Schema::table('configurator_frame_components', function ($table) {
            $table->dropColumn('glass_thicknesses');
        });
    }
};
