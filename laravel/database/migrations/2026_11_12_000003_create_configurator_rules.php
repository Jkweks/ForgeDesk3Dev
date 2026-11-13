<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configurator rules (Configurator Admin > Rules): "when these conditions hold, do these actions"
 * applied after the standard frame / door / hardware BOMs are generated.
 *
 * Also: a length on backers (so a rule can reference e.g. P2451.length), and a push / pull side on
 * hardware variables and items so a push bar is only asked for push values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurator_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->boolean('enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('conditions')->nullable();
            $table->json('actions')->nullable();
            $table->timestamps();
        });

        foreach (['door_frame_frame_parts', 'door_frame_door_parts', 'door_frame_hardware_parts'] as $parts) {
            Schema::table($parts, function (Blueprint $table) {
                $table->foreignId('rule_id')->nullable()->constrained('configurator_rules')->nullOnDelete();
            });
        }

        // Per scope (frame / door / hardware): what the rules depended on when that BOM was last
        // generated, so a later hardware or rule change can flag the BOM as out of date.
        Schema::table('door_frame_configurations', function (Blueprint $table) {
            $table->json('rule_signatures')->nullable();
        });

        Schema::table('configurator_hwlib_backers', function (Blueprint $table) {
            $table->decimal('length', 8, 4)->nullable();
        });

        Schema::table('configurator_hwlib_variables', function (Blueprint $table) {
            $table->string('push_pull', 10)->nullable();
        });
        Schema::table('configurator_hwlib_items', function (Blueprint $table) {
            $table->string('push_pull', 10)->nullable();
        });

        // The existing push/pull prep variables are named for their side. PUSH_PULL_* applies to both.
        DB::table('configurator_hwlib_variables')->where('code', 'like', 'PUSH\_%')
            ->where('code', 'not like', 'PUSH\_PULL%')->update(['push_pull' => 'push']);
        DB::table('configurator_hwlib_variables')->where('code', 'like', 'PULL\_%')->update(['push_pull' => 'pull']);
    }

    public function down(): void
    {
        Schema::table('configurator_hwlib_items', fn (Blueprint $table) => $table->dropColumn('push_pull'));
        Schema::table('configurator_hwlib_variables', fn (Blueprint $table) => $table->dropColumn('push_pull'));
        Schema::table('configurator_hwlib_backers', fn (Blueprint $table) => $table->dropColumn('length'));
        Schema::table('door_frame_configurations', fn (Blueprint $table) => $table->dropColumn('rule_signatures'));
        foreach (['door_frame_frame_parts', 'door_frame_door_parts', 'door_frame_hardware_parts'] as $parts) {
            Schema::table($parts, function (Blueprint $table) {
                $table->dropConstrainedForeignId('rule_id');
            });
        }
        Schema::dropIfExists('configurator_rules');
    }
};
