<?php

use App\Models\ConfiguratorHwlibVariable;
use App\Services\Configurator\HwlibFormulaConverter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurator_hwlib_variables', function (Blueprint $table) {
            $table->json('formula_def')->nullable()->after('formula');
        });

        // Convert the text formulas (incl. the three formerly hard-coded conditional ones).
        // A formula that can't be represented keeps formula_def = null and its text.
        foreach (ConfiguratorHwlibVariable::whereNotNull('formula')->get() as $v) {
            $def = HwlibFormulaConverter::fromLegacy($v->code, $v->formula);
            if ($def) {
                $v->forceFill(['formula_def' => $def, 'is_calculated' => true])->save();
            }
        }
    }

    public function down(): void
    {
        Schema::table('configurator_hwlib_variables', function (Blueprint $table) {
            $table->dropColumn('formula_def');
        });
    }
};
