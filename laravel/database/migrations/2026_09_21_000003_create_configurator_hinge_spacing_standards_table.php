<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Butt-hinge spacing standards (door-catalog-style flat lookup table, admin-
 * managed from the Door Catalog tab). Each row is a top/bottom rule pair:
 * a distance measured from door top (to top/center of prep, per top_label),
 * and a distance measured from either the door bottom or the finished floor
 * (to top/center/bottom of prep, per bottom_label) — floor-referenced rows
 * are converted to "distance from door bottom" using configurator_settings'
 * bottom_gap. Any hinges beyond the top/bottom pair are spaced evenly
 * between them. See ConfiguratorHingeSpacingStandard::locations().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurator_hinge_spacing_standards', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->decimal('top_distance', 8, 4);
            $table->string('top_label', 50)->default('top of prep');
            $table->decimal('bottom_distance', 8, 4);
            $table->enum('bottom_reference', ['door_bottom', 'floor'])->default('door_bottom');
            $table->string('bottom_label', 50)->default('bottom of prep');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::table('configurator_hinge_spacing_standards')->insert([
            [
                'name' => 'Standard',
                'top_distance' => 2.9375,
                'top_label' => 'top of prep',
                'bottom_distance' => 3.0,
                'bottom_reference' => 'door_bottom',
                'bottom_label' => 'bottom of prep',
                'notes' => '2-15/16" from door top to top of prep; 3" from door bottom to bottom of prep.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Curries',
                'top_distance' => 7.25,
                'top_label' => 'center of prep',
                'bottom_distance' => 12.25,
                'bottom_reference' => 'floor',
                'bottom_label' => 'center of prep',
                'notes' => '7-1/4" from door top to center of prep; 12-1/4" from finished floor to center of prep.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configurator_hinge_spacing_standards');
    }
};
