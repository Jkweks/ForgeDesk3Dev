<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('door_frame_frame_configs', function (Blueprint $table) {
            $table->foreignId('frame_series_id')->nullable()->after('configuration_id')
                ->constrained('configurator_frame_series');
            $table->boolean('has_threshold')->default(false)->after('has_transom');
        });

        // frame_series_id now drives BOM generation; the legacy product FK is no longer required.
        // No rows exist yet for this unused table, so drop/re-add (nullable) is safe and works
        // identically across Postgres and SQLite, unlike a raw ALTER COLUMN ... DROP NOT NULL.
        // The original migration also declared a separate explicit index on this column.
        // Postgres and SQLite disagree on whether it actually got created, and Postgres
        // aborts the whole (transactional) migration on a failed statement, so check for
        // its existence first rather than attempting the drop and catching a failure.
        $indexName = 'door_frame_frame_configs_frame_system_product_id_index';
        $indexExists = match (DB::getDriverName()) {
            'pgsql' => (bool) DB::selectOne('SELECT 1 FROM pg_indexes WHERE indexname = ?', [$indexName]),
            'sqlite' => (bool) DB::selectOne("SELECT 1 FROM sqlite_master WHERE type = 'index' AND name = ?", [$indexName]),
            default => true,
        };
        if ($indexExists) {
            Schema::table('door_frame_frame_configs', function (Blueprint $table) {
                $table->dropIndex(['frame_system_product_id']);
            });
        }

        Schema::table('door_frame_frame_configs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('frame_system_product_id');
        });
        Schema::table('door_frame_frame_configs', function (Blueprint $table) {
            $table->foreignId('frame_system_product_id')->nullable()->after('frame_series_id')
                ->constrained('products');
        });

        Schema::table('door_frame_frame_parts', function (Blueprint $table) {
            $table->decimal('quantity', 10, 3)->default(1)->after('calculated_length');
            $table->enum('unit_type', ['length', 'qty'])->default('length')->after('quantity');
            $table->enum('source_type', ['profile', 'component', 'fastener', 'manual'])->default('manual')->after('unit_type');
            $table->boolean('is_auto_generated')->default(false)->after('source_type');
        });
    }

    public function down(): void
    {
        Schema::table('door_frame_frame_parts', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'unit_type', 'source_type', 'is_auto_generated']);
        });

        Schema::table('door_frame_frame_configs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('frame_series_id');
            $table->dropColumn('has_threshold');
        });
    }
};
