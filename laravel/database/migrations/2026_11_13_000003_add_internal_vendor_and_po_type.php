<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PM Suite integration groundwork (docs/plans/pm-suite-integration.md).
 *
 * - suppliers.is_internal: vendor is an internal department (e.g. Vos Glass - Fabrication).
 * - purchase_orders.type: stock | job | internal. Existing rows are stock POs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->boolean('is_internal')->default(false)->after('is_active');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('type', 20)->default('stock')->after('status');
            $table->index('type');
        });

        $existing = DB::table('suppliers')->where('name', 'Vos Glass - Fabrication')->first();
        if ($existing) {
            DB::table('suppliers')->where('id', $existing->id)->update(['is_internal' => true]);
        } else {
            DB::table('suppliers')->insert([
                'name' => 'Vos Glass - Fabrication',
                'code' => 'VOS-FAB',
                'is_active' => true,
                'is_internal' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('is_internal');
        });
    }
};
