<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Programming steps default to the configured programmer (config/fabrication.php). Points every programming
 * stage template at them and reassigns the programming steps still to do; finished steps keep whoever did them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $name = config('fabrication.default_programmer');
        $userId = $name ? DB::table('fd_users')->where('active', true)->where('name', $name)->value('id') : null;
        if (! $userId) {
            return;
        }

        DB::table('fd_stage_templates')
            ->whereRaw("LOWER(name) LIKE '%program%'")
            ->update(['default_user_id' => $userId]);

        $stageIds = DB::table('fd_wo_stages')
            ->whereRaw("LOWER(name) LIKE '%program%'")
            ->whereIn('status', ['pending', 'in_progress'])
            ->pluck('id');

        foreach ($stageIds->chunk(500) as $chunk) {
            DB::table('fd_wo_stage_assignees')->whereIn('stage_id', $chunk)->delete();
            DB::table('fd_wo_stage_assignees')->insert($chunk->map(fn ($id) => [
                'stage_id' => $id, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ])->all());
            DB::table('fd_wo_stages')->whereIn('id', $chunk)->update(['assigned_to_id' => $userId]);
        }
    }

    public function down(): void
    {
        // Assignments are data; there is nothing safe to undo.
    }
};
