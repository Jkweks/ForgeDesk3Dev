<?php

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every product must have a supplier from here on — a null supplier_id was
 * silently dropping products from Replenishment (it filters `p.supplier_id`
 * truthy client-side, see operations/replenishment.blade.php). Backfill
 * existing supplier-less products onto a placeholder "No Supplier" row
 * (looked up by `code`, not `name`) so nothing gets lost, then enforce it
 * at the DB level so it can't happen again regardless of entry point.
 */
return new class extends Migration
{
    public function up(): void
    {
        $placeholder = Supplier::firstOrCreate(
            ['code' => 'NO_SUPPLIER'],
            ['name' => 'No Supplier', 'is_active' => true]
        );

        Product::whereNull('supplier_id')->update(['supplier_id' => $placeholder->id]);

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('supplier_id')->nullable(false)->change();
            });
        } else {
            DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_supplier_id_foreign');
            DB::statement('ALTER TABLE products ALTER COLUMN supplier_id SET NOT NULL');
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_supplier_id_foreign FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT');
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('supplier_id')->nullable()->change();
            });
        } else {
            DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_supplier_id_foreign');
            DB::statement('ALTER TABLE products ALTER COLUMN supplier_id DROP NOT NULL');
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_supplier_id_foreign FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL');
        }

        // Data backfill and the placeholder Supplier row are left in place —
        // reversing them isn't safe to infer and isn't necessary to undo the
        // constraint change itself.
    }
};
