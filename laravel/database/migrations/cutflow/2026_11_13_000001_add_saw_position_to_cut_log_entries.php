<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'cutflow';

    public function up(): void
    {
        Schema::connection('cutflow')->table('cut_log_entries', function (Blueprint $table) {
            // Target the TigerStop amp itself echoed for this cut (tiger-bridge >= 0.5.0), as opposed to
            // dimension_inches, which is what we asked for. Null = never confirmed (old bridge, manual cut by hand).
            $table->decimal('saw_position_inches', 8, 3)->nullable()->after('dimension_inches');
        });
    }

    public function down(): void
    {
        Schema::connection('cutflow')->table('cut_log_entries', function (Blueprint $table) {
            $table->dropColumn('saw_position_inches');
        });
    }
};
