<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The database keeps products.quantity_on_hand equal to the sum of the product's live (not soft-deleted)
 * inventory_locations.quantity, whatever code or manual SQL changes a bin. Existing rows are NOT rewritten
 * here: run `php artisan inventory:check-totals` to see (and optionally --fix) any drift first.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sum = fn (string $pid) => "COALESCE((SELECT SUM(quantity) FROM inventory_locations WHERE product_id = {$pid} AND deleted_at IS NULL), 0)";

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<SQL
CREATE OR REPLACE FUNCTION fd_sync_product_quantity() RETURNS trigger AS \$\$
BEGIN
    IF TG_OP <> 'DELETE' THEN
        UPDATE products SET quantity_on_hand = {$sum('NEW.product_id')} WHERE id = NEW.product_id;
    END IF;
    IF TG_OP = 'DELETE' OR (TG_OP = 'UPDATE' AND OLD.product_id <> NEW.product_id) THEN
        UPDATE products SET quantity_on_hand = {$sum('OLD.product_id')} WHERE id = OLD.product_id;
    END IF;
    RETURN NULL;
END;
\$\$ LANGUAGE plpgsql;
SQL);
            DB::unprepared('DROP TRIGGER IF EXISTS fd_sync_product_quantity ON inventory_locations');
            DB::unprepared('CREATE TRIGGER fd_sync_product_quantity AFTER INSERT OR UPDATE OR DELETE ON inventory_locations FOR EACH ROW EXECUTE FUNCTION fd_sync_product_quantity()');

            return;
        }

        // SQLite (test suite): one trigger per event
        foreach (['ai' => ['INSERT', 'NEW'], 'au' => ['UPDATE', 'NEW'], 'ad' => ['DELETE', 'OLD']] as $suffix => [$event, $row]) {
            DB::unprepared("DROP TRIGGER IF EXISTS fd_il_sync_{$suffix}");
            DB::unprepared("CREATE TRIGGER fd_il_sync_{$suffix} AFTER {$event} ON inventory_locations BEGIN UPDATE products SET quantity_on_hand = {$sum($row.'.product_id')} WHERE id = {$row}.product_id; END");
        }
        // A bin moved to another product also changes the old product's total
        DB::unprepared('DROP TRIGGER IF EXISTS fd_il_sync_au_old');
        DB::unprepared("CREATE TRIGGER fd_il_sync_au_old AFTER UPDATE OF product_id ON inventory_locations WHEN OLD.product_id <> NEW.product_id BEGIN UPDATE products SET quantity_on_hand = {$sum('OLD.product_id')} WHERE id = OLD.product_id; END");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS fd_sync_product_quantity ON inventory_locations');
            DB::unprepared('DROP FUNCTION IF EXISTS fd_sync_product_quantity()');

            return;
        }
        foreach (['ai', 'au', 'ad', 'au_old'] as $suffix) {
            DB::unprepared("DROP TRIGGER IF EXISTS fd_il_sync_{$suffix}");
        }
    }
};
