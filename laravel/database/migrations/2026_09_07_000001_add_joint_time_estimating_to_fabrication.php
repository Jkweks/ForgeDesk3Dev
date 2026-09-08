<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Joint-based labour-time estimating, restructured for the tier system:
 *
 *  - each stage template carries a "minutes per joint" rate (setup module),
 *    copied onto every work-order stage when the elevation is seeded/rebuilt
 *  - a tier carries its own "minutes per joint" fallback, used when none of its
 *    steps set an individual rate
 *  - an elevation line carries a joint quantity; its estimate is
 *    joint_qty x (summed step rates, or the tier fallback)
 *  - the work order carries an optional manual override for the whole roll-up
 *
 * Every figure is optional — a null just contributes nothing to the total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_stage_template_sets', function (Blueprint $table) {
            $table->decimal('minutes_per_joint', 8, 2)->nullable()->after('is_default');
        });

        Schema::table('fd_stage_templates', function (Blueprint $table) {
            $table->decimal('minutes_per_joint', 8, 2)->nullable()->after('blocks_next');
        });

        Schema::table('fd_wo_stages', function (Blueprint $table) {
            $table->decimal('minutes_per_joint', 8, 2)->nullable()->after('blocks_next');
        });

        Schema::table('fd_wo_elevations', function (Blueprint $table) {
            $table->unsignedInteger('joint_qty')->nullable()->after('quantity');
        });

        Schema::table('fd_work_orders', function (Blueprint $table) {
            $table->unsignedInteger('estimated_minutes_override')->nullable()->after('material_delivery');
        });
    }

    public function down(): void
    {
        Schema::table('fd_stage_template_sets', fn (Blueprint $table) => $table->dropColumn('minutes_per_joint'));
        Schema::table('fd_stage_templates', fn (Blueprint $table) => $table->dropColumn('minutes_per_joint'));
        Schema::table('fd_wo_stages', fn (Blueprint $table) => $table->dropColumn('minutes_per_joint'));
        Schema::table('fd_wo_elevations', fn (Blueprint $table) => $table->dropColumn('joint_qty'));
        Schema::table('fd_work_orders', fn (Blueprint $table) => $table->dropColumn('estimated_minutes_override'));
    }
};
