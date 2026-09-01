<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-operator stage assignment. A stage can now be assigned to several
 * fab users at once — it shows in every assignee's queue, and because status
 * lives on the stage row itself, one operator completing it clears it for all.
 *
 * `fd_wo_stages.assigned_to_id` is kept as the "primary assignee" mirror
 * (first pivot row) so legacy single-assignee reads keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fd_wo_stage_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('fd_wo_stages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('fd_users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['stage_id', 'user_id']);
        });

        // Backfill from the existing single-assignee column.
        $now = now();
        DB::table('fd_wo_stages')
            ->whereNotNull('assigned_to_id')
            ->select('id', 'assigned_to_id')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($now) {
                DB::table('fd_wo_stage_assignees')->insertOrIgnore(
                    $rows->map(fn ($r) => [
                        'stage_id'   => $r->id,
                        'user_id'    => $r->assigned_to_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('fd_wo_stage_assignees');
    }
};
