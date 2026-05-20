<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_stage_templates', function (Blueprint $table) {
            $table->foreignId('elevation_type_id')
                ->nullable()
                ->after('id')
                ->constrained('fd_elevation_types')
                ->nullOnDelete();
        });

        // Migrate existing job_type rows to link to the new elevation type records
        $typeMap = ['SF' => 'SF', 'CW' => 'CW'];
        foreach ($typeMap as $jobType => $typeName) {
            $typeId = DB::table('fd_elevation_types')->where('name', $typeName)->value('id');
            if ($typeId) {
                DB::table('fd_stage_templates')
                    ->where('job_type', $jobType)
                    ->update(['elevation_type_id' => $typeId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('fd_stage_templates', function (Blueprint $table) {
            $table->dropForeign(['elevation_type_id']);
            $table->dropColumn('elevation_type_id');
        });
    }
};
