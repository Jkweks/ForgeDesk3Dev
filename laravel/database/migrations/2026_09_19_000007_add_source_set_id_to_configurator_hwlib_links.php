<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tags a hardware link with the set it was materialized from, so that
 * re-applying a set (after it's edited) can find and replace only the links
 * it previously created, leaving manually-added links untouched. Deleting a
 * set definition doesn't delete hardware already cut into an opening — it
 * just orphans the provenance tag (nullOnDelete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurator_hwlib_links', function (Blueprint $table) {
            $table->foreignId('source_set_id')->nullable()->after('item_id')
                ->constrained('configurator_hwlib_sets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('configurator_hwlib_links', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_set_id');
        });
    }
};
