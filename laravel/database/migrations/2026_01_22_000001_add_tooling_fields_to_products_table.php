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
        Schema::table('products', function (Blueprint $table) {
            // Tool type classification
            $table->enum('tool_type', ['consumable_tool', 'asset_tool'])->nullable()->after('configurator_use_path');

            // Tool life tracking fields (only for consumable_tool)
            $table->decimal('tool_life_max', 10, 2)->nullable()->after('tool_type')->comment('Maximum tool life (e.g., 1000 cycles, 500 hours)');
            $table->enum('tool_life_unit', ['seconds', 'minutes', 'hours', 'cycles', 'parts', 'meters'])->nullable()->after('tool_life_max');
            $table->integer('tool_life_warning_threshold')->nullable()->after('tool_life_unit')->comment('Percentage threshold to warn (e.g., 80 for 80%)');

            // Machine compatibility
            $table->json('compatible_machine_types')->nullable()->after('tool_life_warning_threshold')->comment('Array of machine_type_ids this tool works with');

            // Tool specifications (diameter, length, coating, material, etc.)
            $table->json('tool_specifications')->nullable()->after('compatible_machine_types')->comment('Tool-specific specs like diameter, length, coating, material');

            // Add indexes for tool fields
            $table->index('tool_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['tool_type']);
            $table->dropColumn([
                'tool_type',
                'tool_life_max',
                'tool_life_unit',
                'tool_life_warning_threshold',
                'compatible_machine_types',
                'tool_specifications',
            ]);
        });
    }
};
