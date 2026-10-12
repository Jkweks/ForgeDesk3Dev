<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurator_door_types', function (Blueprint $table) {
            // Rail labels (configurator_rails.label) the configurator pre-selects for this stile.
            $table->string('std_top_rail_label', 50)->nullable()->after('stile_height');
            $table->string('std_bot_rail_label', 50)->nullable()->after('std_top_rail_label');
        });
    }

    public function down(): void
    {
        Schema::table('configurator_door_types', function (Blueprint $table) {
            $table->dropColumn(['std_top_rail_label', 'std_bot_rail_label']);
        });
    }
};
