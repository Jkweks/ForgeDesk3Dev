<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A real, stored link from a production elevation to the configurator
 * record that specs it — previously only re-derivable by matching
 * elevation_tag against DoorFrameConfigurationDoor.door_tag at call time.
 * Nullable/null-on-delete: deleting a configuration never cascades into
 * deleting real production elevation data, it just orphans the link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_wo_elevations', function (Blueprint $table) {
            $table->foreignId('door_frame_configuration_id')->nullable()->after('elevation_type_id')
                ->constrained('door_frame_configurations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fd_wo_elevations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('door_frame_configuration_id');
        });
    }
};
