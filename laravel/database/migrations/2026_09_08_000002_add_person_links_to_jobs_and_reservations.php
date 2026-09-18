<?php

use App\Models\BusinessJob;
use App\Models\JobReservation;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Start linking reservations and jobs to real user accounts. The free-text
 * `requested_by` / `project_manager` columns stay as a synced display label
 * ("Lastname, Firstname"); the new *_id columns are the actual link.
 *
 * Backfill matches existing names to a user (any of "First Last", "Last, First",
 * or the stored `name`). Unmatched rows keep just their text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_reservations', function (Blueprint $table) {
            $table->foreignId('requested_by_id')->nullable()->after('requested_by')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('business_jobs', function (Blueprint $table) {
            $table->foreignId('project_manager_id')->nullable()->after('project_manager')
                ->constrained('users')->nullOnDelete();
        });

        $index = $this->userNameIndex();

        JobReservation::query()->whereNotNull('requested_by')->where('requested_by', '!=', '')
            ->chunkById(200, function ($rows) use ($index) {
                foreach ($rows as $row) {
                    if ($id = $index[$this->norm($row->requested_by)] ?? null) {
                        $row->forceFill(['requested_by_id' => $id])->saveQuietly();
                    }
                }
            });

        BusinessJob::query()->whereNotNull('project_manager')->where('project_manager', '!=', '')
            ->chunkById(200, function ($rows) use ($index) {
                foreach ($rows as $row) {
                    if ($id = $index[$this->norm($row->project_manager)] ?? null) {
                        $row->forceFill(['project_manager_id' => $id])->saveQuietly();
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('job_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by_id');
        });
        Schema::table('business_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_manager_id');
        });
    }

    /** name-variant (normalised) => user id */
    private function userNameIndex(): array
    {
        $index = [];
        foreach (User::query()->get(['id', 'first_name', 'last_name', 'name']) as $u) {
            foreach ([
                $u->name,
                trim("{$u->first_name} {$u->last_name}"),
                trim("{$u->last_name}, {$u->first_name}", ', '),
            ] as $variant) {
                $key = $this->norm($variant);
                if ($key !== '' && ! isset($index[$key])) {
                    $index[$key] = $u->id;
                }
            }
        }

        return $index;
    }

    private function norm(?string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $value)));
    }
};
