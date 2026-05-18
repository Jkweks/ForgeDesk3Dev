<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FabricationSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('fd_rate_constants')->insertOrIgnore([
            ['key' => 'cw_prep',    'value' => 0.5,  'description' => 'hrs/joint for CW prep',    'created_at' => now(), 'updated_at' => now()],
            ['key' => 'fab_joints', 'value' => 0.25, 'description' => 'hrs/joint for SF fab',      'created_at' => now(), 'updated_at' => now()],
            ['key' => 'fab_doors',  'value' => 2.25, 'description' => 'hrs/unit for door fab',     'created_at' => now(), 'updated_at' => now()],
            ['key' => 'fab_frames', 'value' => 1.5,  'description' => 'hrs/unit for frame fab',    'created_at' => now(), 'updated_at' => now()],
        ]);

        $sfStages = [
            ['name' => 'Material Check',    'description' => 'Confirm all material on-site',               'sort_order' => 1],
            ['name' => 'Cut List Released', 'description' => 'Cut list sent to shop floor',                'sort_order' => 2],
            ['name' => 'Frame Fab',         'description' => 'Frame fabrication in progress',              'sort_order' => 3],
            ['name' => 'Door Fab',          'description' => 'Door fabrication in progress',               'sort_order' => 4],
            ['name' => 'Hardware Install',  'description' => 'Hardware installation and prep',             'sort_order' => 5],
            ['name' => 'QC & Pack',         'description' => 'Quality control check and pack for delivery', 'sort_order' => 6],
        ];

        $cwStages = [
            ['name' => 'Material Check', 'description' => 'Confirm all CW material on-site',       'sort_order' => 1],
            ['name' => 'CW Prep',        'description' => 'Curtain wall prep and layout',           'sort_order' => 2],
            ['name' => 'Member Fab',     'description' => 'Member fabrication',                     'sort_order' => 3],
            ['name' => 'Glazing Prep',   'description' => 'Glazing preparation',                    'sort_order' => 4],
            ['name' => 'QC & Pack',      'description' => 'Quality control check and pack for delivery', 'sort_order' => 5],
        ];

        foreach ($sfStages as $stage) {
            DB::table('fd_stage_templates')->insertOrIgnore([
                'job_type'    => 'SF',
                'name'        => $stage['name'],
                'description' => $stage['description'],
                'sort_order'  => $stage['sort_order'],
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        foreach ($cwStages as $stage) {
            DB::table('fd_stage_templates')->insertOrIgnore([
                'job_type'    => 'CW',
                'name'        => $stage['name'],
                'description' => $stage['description'],
                'sort_order'  => $stage['sort_order'],
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }
}
