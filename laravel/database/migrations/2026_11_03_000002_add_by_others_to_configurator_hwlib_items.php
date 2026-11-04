<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "By others" hardware is supplied by someone else (e.g. the ASA strike): it is listed on the
 * opening's hardware list but never committed against inventory, while its backers and fasteners
 * (which VOS does supply) are still generated and reserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurator_hwlib_items', function (Blueprint $table) {
            $table->boolean('by_others')->default(false)->after('field_install');
        });

        DB::table('configurator_hwlib_items')->where('pn', 'ASA')->update(['by_others' => true]);
    }

    public function down(): void
    {
        Schema::table('configurator_hwlib_items', function (Blueprint $table) {
            $table->dropColumn('by_others');
        });
    }
};
