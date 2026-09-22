<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Butt-hinge count + spacing standard, entered on the Opening tab only when
 * hinging = butt. Drives auto-filled hinge hardware quantity and the
 * computed hinge prep locations shown on the Opening tab and cut-sheet PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('door_frame_opening_specs', function (Blueprint $table) {
            $table->unsignedTinyInteger('butt_hinge_count')->nullable()->after('hinging');
            $table->foreignId('hinge_spacing_standard_id')->nullable()->after('butt_hinge_count')
                ->constrained('configurator_hinge_spacing_standards')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('door_frame_opening_specs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hinge_spacing_standard_id');
            $table->dropColumn('butt_hinge_count');
        });
    }
};
