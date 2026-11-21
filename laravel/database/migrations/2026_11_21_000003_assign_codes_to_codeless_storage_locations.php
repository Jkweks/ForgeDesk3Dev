<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Locations created without a code (store() used to drop the auto-generated one) printed their raw
 * name on labels. Give them the hyphenated code from the name: "A.4.3" -> "A-4-3".
 */
return new class extends Migration
{
    public function up(): void
    {
        $taken = DB::table('storage_locations')->whereNotNull('code')->pluck('code')->flip()->all();

        DB::table('storage_locations')->whereNull('code')->orderBy('id')->get(['id', 'name'])
            ->each(function ($loc) use (&$taken) {
                $code = substr(trim(preg_replace('/[^A-Z0-9]+/', '-', strtoupper($loc->name)), '-'), 0, 30);
                if ($code === '' || isset($taken[$code])) {
                    return; // leave blank rather than invent a suffix; label falls back to the name
                }
                $taken[$code] = true;
                DB::table('storage_locations')->where('id', $loc->id)->update(['code' => $code]);
            });
    }

    public function down(): void
    {
        // Not reversible: can't tell which codes were assigned here.
    }
};
