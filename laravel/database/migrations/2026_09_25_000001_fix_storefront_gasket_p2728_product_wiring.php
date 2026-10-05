<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Standard Storefront Gasket" frame components were wired to the bare P2728
 * catalog placeholder (never stocked, quantity_on_hand always 0), instead of
 * either of the two real roll SKUs actually carried in inventory:
 * P2728-250 (250ft roll) and P2728-500 (500ft roll) — interchangeable, per the
 * user. Neither roll SKU was flagged length-based either, so even a correctly
 * wired component would still reserve feet-as-eaches (see the FrameBomGenerator
 * fix in the same change).
 *
 * Defaults every "Standard Storefront Gasket" frame component to the 250ft
 * roll (P2728-250); the 500ft roll (P2728-500) is flagged the same way so it
 * can be swapped in manually per job.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->where('sku', 'P2728-250-0R')->update([
            'is_length_based' => true,
            'configurator_length' => 3000, // 250ft
        ]);

        DB::table('products')->where('sku', 'P2728-500-0R')->update([
            'is_length_based' => true,
            'configurator_length' => 6000, // 500ft
        ]);

        $placeholderId = DB::table('products')->where('sku', 'P2728-0R')->value('id');
        $defaultRollId = DB::table('products')->where('sku', 'P2728-250-0R')->value('id');

        if ($placeholderId && $defaultRollId) {
            DB::table('configurator_frame_components')
                ->where('product_id', $placeholderId)
                ->update(['product_id' => $defaultRollId]);
        }
    }

    public function down(): void
    {
        $placeholderId = DB::table('products')->where('sku', 'P2728-0R')->value('id');
        $defaultRollId = DB::table('products')->where('sku', 'P2728-250-0R')->value('id');

        if ($placeholderId && $defaultRollId) {
            DB::table('configurator_frame_components')
                ->where('product_id', $defaultRollId)
                ->update(['product_id' => $placeholderId]);
        }

        DB::table('products')->where('sku', 'P2728-250-0R')->update([
            'is_length_based' => false,
            'configurator_length' => null,
        ]);

        DB::table('products')->where('sku', 'P2728-500-0R')->update([
            'is_length_based' => false,
            'configurator_length' => null,
        ]);
    }
};
