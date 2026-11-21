<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FAB storage locations got codes like FABE24 (separators stripped) or no code at all, so
 * their labels printed run together / with dots. Give them hyphenated codes: FAB-E-2-4.
 */
return new class extends Migration
{
    public function up(): void
    {
        $taken = DB::table('storage_locations')->whereNotNull('code')->pluck('code')->flip()->all();

        DB::table('storage_locations')
            ->whereRaw("upper(name) like 'FAB%'")
            ->orderBy('id')
            ->get(['id', 'name', 'code'])
            ->each(function ($loc) use (&$taken) {
                $want = trim(preg_replace('/[^A-Z0-9]+/', '-', strtoupper($loc->name)), '-');
                if ($want === '' || $loc->code === $want) {
                    return;
                }
                // Only replace codes that were auto-generated from the name (or missing).
                $stripped = str_replace('-', '', $want);
                if ($loc->code !== null && $loc->code !== $stripped && ! str_starts_with($loc->code, $stripped)) {
                    return;
                }
                if (isset($taken[$want])) {
                    return;
                }
                unset($taken[$loc->code]);
                $taken[$want] = true;
                DB::table('storage_locations')->where('id', $loc->id)->update(['code' => $want]);
            });
    }

    public function down(): void
    {
        // Not reversible: the old codes were lossy.
    }
};
