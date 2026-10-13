<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Face width of an extrusion in inches (jamb / vertical). Used by the sill quarter-point report.
            $table->decimal('dimension_width', 10, 4)->nullable()->after('dimension_depth');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('dimension_width');
        });
    }
};
