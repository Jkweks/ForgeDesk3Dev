<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Point each stage template at a tier. Backfill: every elevation type gets one
 * "Standard" default tier that owns its existing templates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_stage_templates', function (Blueprint $table) {
            $table->foreignId('template_set_id')->nullable()->after('elevation_type_id')
                ->constrained('fd_stage_template_sets')->nullOnDelete();
        });

        foreach (DB::table('fd_elevation_types')->pluck('id') as $typeId) {
            $setId = DB::table('fd_stage_template_sets')->insertGetId([
                'elevation_type_id' => $typeId,
                'name'       => 'Standard',
                'sort_order' => 0,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('fd_stage_templates')
                ->where('elevation_type_id', $typeId)
                ->whereNull('template_set_id')
                ->update(['template_set_id' => $setId]);
        }
    }

    public function down(): void
    {
        Schema::table('fd_stage_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_set_id');
        });
    }
};
