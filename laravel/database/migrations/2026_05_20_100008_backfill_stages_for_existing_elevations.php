<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Find elevations that have an elevation_type but zero stages
        $elevations = DB::table('fd_wo_elevations')
            ->whereNotNull('elevation_type_id')
            ->whereNotIn('id', function ($sub) {
                $sub->select('elevation_id')
                    ->from('fd_wo_stages')
                    ->whereNotNull('elevation_id');
            })
            ->get();

        foreach ($elevations as $elevation) {
            $templates = DB::table('fd_stage_templates')
                ->where('elevation_type_id', $elevation->elevation_type_id)
                ->orderBy('sort_order')
                ->get();

            foreach ($templates as $tpl) {
                DB::table('fd_wo_stages')->insert([
                    'elevation_id'  => $elevation->id,
                    'work_order_id' => null,
                    'template_id'   => $tpl->id,
                    'name'          => $tpl->name,
                    'description'   => $tpl->description,
                    'sort_order'    => $tpl->sort_order,
                    'status'        => 'pending',
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Remove stages that have no started/completed timestamps (only backfilled ones)
        DB::table('fd_wo_stages')
            ->whereNotNull('elevation_id')
            ->whereNull('started_at')
            ->whereNull('completed_at')
            ->delete();
    }
};
