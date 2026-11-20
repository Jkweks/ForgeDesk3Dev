<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Enables fuzzy (typo-tolerant) search via App\Support\Search. Best-effort: if the DB role
// can't create the extension, search still works (case/token/separator/regex), minus fuzzy.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        try {
            DB::transaction(fn () => DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm'));
        } catch (\Throwable $e) {
            logger()->warning('pg_trgm not enabled; fuzzy search disabled: '.$e->getMessage());
        }
    }

    public function down(): void
    {
        // leave the extension in place
    }
};
