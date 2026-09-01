<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The `status` column on fd_wo_stages / fd_job_steps has always been a free
 * string. Gating logic now depends on a fixed vocabulary, so fold any stray
 * values into it: map known synonyms explicitly, then anything still unknown
 * falls back to `pending` (safest — it never fabricates a "done" state).
 *
 * `down()` is intentionally a no-op; the original stray values are not worth
 * restoring and were never valid.
 */
return new class extends Migration
{
    private const STAGE_STATUSES = ['pending', 'in_progress', 'complete', 'blocked', 'not_required', 'on_hold'];
    private const STEP_STATUSES  = ['pending', 'complete', 'not_required', 'on_hold'];

    private const SYNONYMS = [
        'done'         => 'complete',
        'completed'    => 'complete',
        'finished'     => 'complete',
        'in progress'  => 'in_progress',
        'in-progress'  => 'in_progress',
        'started'      => 'in_progress',
        'wip'          => 'in_progress',
        'n/a'          => 'not_required',
        'na'           => 'not_required',
        'skip'         => 'not_required',
        'skipped'      => 'not_required',
        'hold'         => 'on_hold',
        'on hold'      => 'on_hold',
        'waiting'      => 'blocked',
    ];

    public function up(): void
    {
        $this->normalize('fd_wo_stages', self::STAGE_STATUSES);
        $this->normalize('fd_job_steps', self::STEP_STATUSES);
    }

    private function normalize(string $table, array $allowed): void
    {
        foreach (self::SYNONYMS as $from => $to) {
            if (! in_array($to, $allowed, true)) {
                continue;
            }
            DB::table($table)
                ->whereRaw('LOWER(TRIM(status)) = ?', [$from])
                ->update(['status' => $to]);
        }

        DB::table($table)
            ->whereNotIn('status', $allowed)
            ->update(['status' => 'pending']);
    }

    public function down(): void
    {
        // no-op
    }
};
