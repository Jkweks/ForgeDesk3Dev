<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers which hardware-library rows came from a fab_utils import, so `--prune` can remove a row fab_utils
 * later dropped without ever touching one a ForgeDesk admin created by hand. The importer stamps the column on
 * every row it matches or creates.
 */
return new class extends Migration
{
    private const TABLES = ['configurator_hwlib_categories', 'configurator_hwlib_items', 'configurator_hwlib_backers', 'configurator_hwlib_fasteners'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn ($t) => $t->timestamp('imported_at')->nullable());
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn ($t) => $t->dropColumn('imported_at'));
        }
    }
};
