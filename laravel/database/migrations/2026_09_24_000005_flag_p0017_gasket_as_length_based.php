<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * P0017 ("Glazing Gasket - 500'") is cut from a 500ft (6000") roll, not stocked
 * as individual eaches. It was missing is_length_based/configurator_length, so
 * ConfigurationReservationBridge::quantityContribution() had no way to convert a
 * calculated gasket length (e.g. 866") into a fraction of a roll and reserved it
 * as 866 whole eaches instead — see also the DoorBomGenerator fix that makes
 * gasket BOM rows carry a length instead of a flat qty in the first place.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where('part_number', 'P0017')
            ->update([
                'is_length_based' => true,
                'configurator_length' => 6000,
            ]);
    }

    public function down(): void
    {
        DB::table('products')
            ->where('part_number', 'P0017')
            ->update([
                'is_length_based' => false,
                'configurator_length' => null,
            ]);
    }
};
