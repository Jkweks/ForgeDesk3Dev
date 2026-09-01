<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Record which tier an elevation was built from. Best-effort backfill: point each
 * already-typed elevation at its type's default tier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_wo_elevations', function (Blueprint $table) {
            $table->foreignId('template_set_id')->nullable()->after('elevation_type_id')
                ->constrained('fd_stage_template_sets')->nullOnDelete();
        });

        $defaults = DB::table('fd_stage_template_sets')
            ->where('is_default', true)
            ->pluck('id', 'elevation_type_id'); // [elevation_type_id => set_id]

        foreach ($defaults as $typeId => $setId) {
            DB::table('fd_wo_elevations')
                ->where('elevation_type_id', $typeId)
                ->whereNull('template_set_id')
                ->update(['template_set_id' => $setId]);
        }
    }

    public function down(): void
    {
        Schema::table('fd_wo_elevations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_set_id');
        });
    }
};
