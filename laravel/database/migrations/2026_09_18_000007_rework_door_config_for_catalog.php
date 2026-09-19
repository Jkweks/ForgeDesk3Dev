<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reworks door_frame_door_configs to match fab_utils' actual door-calculator
 * inputs (checked against configurator/door-calculator.html's calculate()):
 * door series/stile drive stile height + stile PN variants from
 * configurator_door_types, rails are picked by label from configurator_rails,
 * glazing is one of 11 real thicknesses (configurator_glass_specs.thickness),
 * not the 3-option enum the original stub had. One row per configuration
 * (like frame_config), covering both leaves of a pair via qty math — not one
 * row per leaf, which the original leaf_type enum implied.
 *
 * door_system_product_id / stile_product_id are dropped: door series+stile
 * are strings resolved against the catalog at BOM-generation time, the same
 * way frame_series replaced frame_system_product_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('door_frame_door_configs', function (Blueprint $table) {
            $table->string('door_series', 20)->nullable()->after('configuration_id');
            $table->string('stile_width', 50)->nullable()->after('door_series');
            $table->string('handing', 30)->nullable()->after('leaf_type');
            $table->string('hinge_type', 30)->nullable()->after('handing');
            $table->unsignedSmallInteger('opening_angle')->default(90)->after('hinge_type');
            $table->decimal('bottom_gap', 6, 4)->default(0.6875)->after('opening_angle');
            $table->string('top_rail_label', 40)->nullable()->after('bottom_gap');
            $table->string('bot_rail_label', 40)->nullable()->after('top_rail_label');
            $table->string('mid_rail_label', 40)->nullable()->after('bot_rail_label');
            $table->unsignedTinyInteger('mid_qty')->default(0)->after('mid_rail_label');
            $table->decimal('mid_loc1', 8, 4)->nullable()->after('mid_qty');
            $table->decimal('mid_loc2', 8, 4)->nullable()->after('mid_loc1');
        });

        // glazing needs to hold real thickness strings ("3/16", "1/4", ... "1"),
        // not the old 3-value enum. The table has no production rows yet, so a
        // straight drop/re-add is safe (no data to migrate).
        Schema::table('door_frame_door_configs', function (Blueprint $table) {
            $table->dropColumn('glazing');
        });
        Schema::table('door_frame_door_configs', function (Blueprint $table) {
            $table->string('glazing', 10)->nullable()->after('bot_rail_label');
        });

        // As with frame_system_product_id (see 2026_09_18_000002), Postgres and
        // SQLite disagree on whether the declared index actually got created,
        // and Postgres aborts the whole (transactional) migration on a failed
        // statement — so check for its existence first rather than assuming.
        $indexName = 'door_frame_door_configs_door_system_product_id_index';
        $indexExists = match (DB::getDriverName()) {
            'pgsql' => (bool) DB::selectOne('SELECT 1 FROM pg_indexes WHERE indexname = ?', [$indexName]),
            'sqlite' => (bool) DB::selectOne("SELECT 1 FROM sqlite_master WHERE type = 'index' AND name = ?", [$indexName]),
            default => true,
        };
        if ($indexExists) {
            Schema::table('door_frame_door_configs', function (Blueprint $table) {
                $table->dropIndex(['door_system_product_id']);
            });
        }

        Schema::table('door_frame_door_configs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('door_system_product_id');
            $table->dropConstrainedForeignId('stile_product_id');
        });

        Schema::table('door_frame_door_parts', function (Blueprint $table) {
            $table->decimal('quantity', 10, 3)->default(1)->after('calculated_length');
            $table->enum('unit_type', ['length', 'qty'])->default('length')->after('quantity');
            $table->enum('source_type', ['extrusion', 'component', 'manual'])->default('manual')->after('unit_type');
        });
    }

    public function down(): void
    {
        Schema::table('door_frame_door_parts', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'unit_type', 'source_type']);
        });

        Schema::table('door_frame_door_configs', function (Blueprint $table) {
            $table->foreignId('door_system_product_id')->nullable()->constrained('products');
            $table->foreignId('stile_product_id')->nullable()->constrained('products');
        });

        Schema::table('door_frame_door_configs', function (Blueprint $table) {
            $table->dropColumn('glazing');
        });
        Schema::table('door_frame_door_configs', function (Blueprint $table) {
            $table->enum('glazing', ['0.25', '0.5', '1.0']);
        });

        Schema::table('door_frame_door_configs', function (Blueprint $table) {
            $table->dropColumn([
                'door_series', 'stile_width', 'handing', 'hinge_type', 'opening_angle',
                'bottom_gap', 'top_rail_label', 'bot_rail_label', 'mid_rail_label',
                'mid_qty', 'mid_loc1', 'mid_loc2',
            ]);
        });
    }
};
