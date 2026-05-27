<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $stages = [
            ['name' => 'Programmed', 'description' => 'Programmed in software', 'sort_order' => 1],
            ['name' => 'CNC',        'description' => 'Cut on CNC machine',      'sort_order' => 2],
            ['name' => 'Assembled',  'description' => 'Assembled and checked',   'sort_order' => 3],
        ];

        foreach (['Door', 'Frame'] as $typeName) {
            $typeId = DB::table('fd_elevation_types')->where('name', $typeName)->value('id');
            if (!$typeId) continue;

            foreach ($stages as $stage) {
                $exists = DB::table('fd_stage_templates')
                    ->where('elevation_type_id', $typeId)
                    ->where('name', $stage['name'])
                    ->exists();

                if (!$exists) {
                    DB::table('fd_stage_templates')->insert([
                        'elevation_type_id' => $typeId,
                        'job_type'          => $typeName,
                        'name'              => $stage['name'],
                        'description'       => $stage['description'],
                        'sort_order'        => $stage['sort_order'],
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('fd_elevation_types')
            ->whereIn('name', ['Door', 'Frame'])
            ->pluck('id');

        DB::table('fd_stage_templates')
            ->whereIn('elevation_type_id', $ids)
            ->whereIn('name', ['Programmed', 'CNC', 'Assembled'])
            ->delete();
    }
};
