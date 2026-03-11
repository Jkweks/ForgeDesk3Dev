<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('maintenance_tasks', function (Blueprint $table) {
            // Convert assigned_to from string to foreign key
            // First, rename the old column
            $table->renameColumn('assigned_to', 'assigned_to_old');
        });

        Schema::table('maintenance_tasks', function (Blueprint $table) {
            // Add new foreign key column
            $table->foreignId('assigned_to')->nullable()->after('frequency')->constrained('users')->onDelete('set null');

            $table->index('assigned_to');
        });

        // Note: You'll need to manually migrate data from assigned_to_old to assigned_to if needed
        // Then drop the old column in a separate migration after data migration

        Schema::table('maintenance_records', function (Blueprint $table) {
            // Convert performed_by from string to foreign key
            $table->renameColumn('performed_by', 'performed_by_old');
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            // Add new foreign key column
            $table->foreignId('performed_by')->nullable()->after('asset_id')->constrained('users')->onDelete('set null');

            $table->index('performed_by');
        });

        // Note: You'll need to manually migrate data from performed_by_old to performed_by if needed
        // Then drop the old column in a separate migration after data migration
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_tasks', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn('assigned_to');
            $table->renameColumn('assigned_to_old', 'assigned_to');
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropForeign(['performed_by']);
            $table->dropColumn('performed_by');
            $table->renameColumn('performed_by_old', 'performed_by');
        });
    }
};
