<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Same fix as the FAB migration, for every other location: convert codes that were auto-generated
 * from the name with separators stripped (FABE24, B24) to the hyphenated form (FAB-E-2-4, B-2-4).
 * Hand-set codes and locations with no code are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $taken = DB::table('storage_locations')->whereNotNull('code')->pluck('code')->flip()->all();

        DB::table('storage_locations')->whereNotNull('code')->orderBy('id')->get(['id', 'name', 'code'])
            ->each(function ($loc) use (&$taken) {
                $want = substr(trim(preg_replace('/[^A-Z0-9]+/', '-', strtoupper($loc->name)), '-'), 0, 30);
                if ($want === '' || $want === $loc->code || isset($taken[$want])) {
                    return;
                }
                // Old generator: uppercase alphanumerics, first 10 chars, optional numeric uniqueness suffix.
                $old = substr(str_replace('-', '', $want), 0, 10);
                $generated = $loc->code === $old
                    || (str_starts_with($loc->code, $old) && ctype_digit(substr($loc->code, strlen($old))));
                if (! $generated) {
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
