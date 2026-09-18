<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fd_elevation_types', function (Blueprint $table) {
            // Extra strings that a work-order import row's "Type" cell may use for
            // this elevation type. Matched case-insensitively, alongside `name`.
            $table->json('aliases')->nullable()->after('name');
        });

        // Seed the obvious spelling variants for the default types. Only touches
        // rows that still have no aliases, so a re-run or a hand edit is safe.
        $seed = [
            'SF' => ['Storefront', 'Store Front'],
            'CW' => ['Curtainwall', 'Curtain Wall', 'CWall'],
            'Frame' => ['HM Frame', 'Hollow Metal Frame'],
        ];

        foreach ($seed as $name => $aliases) {
            DB::table('fd_elevation_types')
                ->where('name', $name)
                ->whereNull('aliases')
                ->update(['aliases' => json_encode($aliases), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('fd_elevation_types', function (Blueprint $table) {
            $table->dropColumn('aliases');
        });
    }
};
