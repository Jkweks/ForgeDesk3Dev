<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop-rack cuts ride on a stick as items with no part (they never touch the cut list),
 * and show in history as cut log entries of type 'drop'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('cutflow')->table('stick_items', function (Blueprint $table) {
            $table->unsignedBigInteger('part_id')->nullable()->change();
            $table->string('kind', 10)->default('piece')->after('part_id');
        });

        $this->setLogTypes(['planned', 'manual', 'drop']);
    }

    public function down(): void
    {
        DB::connection('cutflow')->table('stick_items')->where('kind', 'drop')->delete();
        DB::connection('cutflow')->table('cut_log_entries')->where('type', 'drop')->delete();

        $this->setLogTypes(['planned', 'manual']);

        Schema::connection('cutflow')->table('stick_items', function (Blueprint $table) {
            $table->dropColumn('kind');
            $table->unsignedBigInteger('part_id')->nullable(false)->change();
        });
    }

    /** Laravel's enum() is a varchar + CHECK on Postgres; widen the CHECK. */
    private function setLogTypes(array $types): void
    {
        $db = DB::connection('cutflow');

        if ($db->getDriverName() !== 'pgsql') {
            return;
        }

        $list = implode(',', array_map(fn ($t) => "'{$t}'", $types));
        $db->statement('ALTER TABLE cut_log_entries DROP CONSTRAINT IF EXISTS cut_log_entries_type_check');
        $db->statement("ALTER TABLE cut_log_entries ADD CONSTRAINT cut_log_entries_type_check CHECK (type::text IN ({$list}))");
    }
};
