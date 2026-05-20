<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $templates = [
            'SF' => [
                ['name' => 'Material Check',    'description' => 'Confirm all material on-site',                'sort_order' => 1],
                ['name' => 'Cut List Released', 'description' => 'Cut list sent to shop floor',                 'sort_order' => 2],
                ['name' => 'Frame Fab',         'description' => 'Frame fabrication in progress',               'sort_order' => 3],
                ['name' => 'Door Fab',          'description' => 'Door fabrication in progress',                'sort_order' => 4],
                ['name' => 'Hardware Install',  'description' => 'Hardware installation and prep',              'sort_order' => 5],
                ['name' => 'QC & Pack',         'description' => 'Quality control check and pack for delivery', 'sort_order' => 6],
            ],
            'CW' => [
                ['name' => 'Material Check', 'description' => 'Confirm all CW material on-site',           'sort_order' => 1],
                ['name' => 'CW Prep',        'description' => 'Curtain wall prep and layout',               'sort_order' => 2],
                ['name' => 'Member Fab',     'description' => 'Member fabrication',                         'sort_order' => 3],
                ['name' => 'Glazing Prep',   'description' => 'Glazing preparation',                        'sort_order' => 4],
                ['name' => 'QC & Pack',      'description' => 'Quality control check and pack for delivery', 'sort_order' => 5],
            ],
        ];

        foreach ($templates as $typeName => $stages) {
            $typeId = DB::table('fd_elevation_types')->where('name', $typeName)->value('id');
            if (!$typeId) continue;

            // Backfill any existing rows seeded without elevation_type_id
            DB::table('fd_stage_templates')
                ->where('job_type', $typeName)
                ->whereNull('elevation_type_id')
                ->update(['elevation_type_id' => $typeId]);

            // Insert missing rows
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
        DB::table('fd_stage_templates')->whereNotNull('elevation_type_id')->delete();
    }
};
