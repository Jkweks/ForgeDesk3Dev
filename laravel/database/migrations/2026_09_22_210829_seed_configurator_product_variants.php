<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the configurator-derived Product placeholders (extrusions, hardware,
 * finish variants) that were created/corrected by hand while reseeding the
 * configurator catalog from fab_utils (2026-09-22). Safe to run against a DB
 * that already ran configurator:import-fab-utils* — every row is upserted by
 * (part_number, finish), so pre-existing/pre-import data is left untouched
 * and re-running this migration is a no-op.
 *
 * Source: verified against fab_utils' EZ Estimate workbook (P Formulas /
 * SL Formulas sheets) — see docs/configurator.md and project memory
 * "configurator-finish-backfill-method" for how these were derived.
 */
return new class extends Migration
{
    public function up(): void
    {
        $supplierId = DB::table('suppliers')->where('id', 1)->value('id')
            ?? DB::table('suppliers')->where('name', 'Tubelite')->value('id')
            ?? DB::table('suppliers')->value('id');

        $rows = [
            ['part_number' => '163036', 'finish' => '0R', 'description' => 'Thermal Subframe', 'manufacturer' => 'Kawneer', 'manufacturer_part_number' => null],
            ['part_number' => '450502', 'finish' => '0R', 'description' => 'Door Head with Transom Dovetails', 'manufacturer' => 'Kawneer', 'manufacturer_part_number' => null],
            ['part_number' => '450520', 'finish' => '0R', 'description' => 'Snap in Door Stop', 'manufacturer' => 'Kawneer', 'manufacturer_part_number' => null],
            ['part_number' => 'A641010', 'finish' => 'BL', 'description' => 'Door Rail 10" - Thermal', 'manufacturer' => null, 'manufacturer_part_number' => '*A641010'],
            ['part_number' => 'A641010', 'finish' => 'C2', 'description' => 'Door Rail 10" - Thermal', 'manufacturer' => null, 'manufacturer_part_number' => '*A641010'],
            ['part_number' => 'A641010', 'finish' => 'DB', 'description' => 'Door Rail 10" - Thermal', 'manufacturer' => null, 'manufacturer_part_number' => '*A641010'],
            ['part_number' => 'A641414', 'finish' => 'BL', 'description' => 'Door Rail 4" - Thermal', 'manufacturer' => null, 'manufacturer_part_number' => '*A641414'],
            ['part_number' => 'A641414', 'finish' => 'C2', 'description' => 'Door Rail 4" - Thermal', 'manufacturer' => null, 'manufacturer_part_number' => '*A641414'],
            ['part_number' => 'A641414', 'finish' => 'DB', 'description' => 'Door Rail 4" - Thermal', 'manufacturer' => null, 'manufacturer_part_number' => '*A641414'],
            ['part_number' => 'A641515', 'finish' => 'BL', 'description' => 'Door Rail 5" - Thermal', 'manufacturer' => null, 'manufacturer_part_number' => '*A641515'],
            ['part_number' => 'A641515', 'finish' => 'C2', 'description' => 'Door Rail 5" - Thermal', 'manufacturer' => null, 'manufacturer_part_number' => '*A641515'],
            ['part_number' => 'A641515', 'finish' => 'DB', 'description' => 'Door Rail 5" - Thermal', 'manufacturer' => null, 'manufacturer_part_number' => '*A641515'],
            ['part_number' => 'A642525', 'finish' => 'BL', 'description' => 'Door Rail 2 1/2" - Thermal', 'manufacturer' => null, 'manufacturer_part_number' => '*A642525'],
            ['part_number' => 'A642525', 'finish' => 'C2', 'description' => 'Door Rail 2 1/2" - Thermal', 'manufacturer' => null, 'manufacturer_part_number' => '*A642525'],
            ['part_number' => 'A642525', 'finish' => 'DB', 'description' => 'Door Rail 2 1/2" - Thermal', 'manufacturer' => null, 'manufacturer_part_number' => '*A642525'],
            ['part_number' => 'A643030', 'finish' => 'BL', 'description' => 'Door Rail 3" - Thermal', 'manufacturer' => null, 'manufacturer_part_number' => '*A643030'],
            ['part_number' => 'A643030', 'finish' => 'C2', 'description' => 'Door Rail 3" - Thermal', 'manufacturer' => null, 'manufacturer_part_number' => '*A643030'],
            ['part_number' => 'A643030', 'finish' => 'DB', 'description' => 'Door Rail 3" - Thermal', 'manufacturer' => null, 'manufacturer_part_number' => '*A643030'],
            ['part_number' => 'A646464', 'finish' => 'BL', 'description' => 'Door Rail 4" - Thermal', 'manufacturer' => null, 'manufacturer_part_number' => '*A646464'],
            ['part_number' => 'A646464', 'finish' => 'C2', 'description' => 'Door Rail 4" - Thermal', 'manufacturer' => null, 'manufacturer_part_number' => '*A646464'],
            ['part_number' => 'A646464', 'finish' => 'DB', 'description' => 'Door Rail 4" - Thermal', 'manufacturer' => null, 'manufacturer_part_number' => '*A646464'],
            ['part_number' => 'A647071', 'finish' => 'BL', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - THERMAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A647071'],
            ['part_number' => 'A647071', 'finish' => 'C2', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - THERMAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A647071'],
            ['part_number' => 'A647071', 'finish' => 'DB', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - THERMAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A647071'],
            ['part_number' => 'A647273', 'finish' => 'BL', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - THERMAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A647273'],
            ['part_number' => 'A647273', 'finish' => 'C2', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - THERMAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A647273'],
            ['part_number' => 'A647273', 'finish' => 'DB', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - THERMAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A647273'],
            ['part_number' => 'A647475', 'finish' => 'BL', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - THERMAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A647475'],
            ['part_number' => 'A647475', 'finish' => 'C2', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - THERMAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A647475'],
            ['part_number' => 'A647475', 'finish' => 'DB', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - THERMAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A647475'],
            ['part_number' => 'A647677', 'finish' => 'BL', 'description' => 'Door Stile (Bevel) - THERMAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A647677'],
            ['part_number' => 'A647677', 'finish' => 'C2', 'description' => 'Door Stile (Bevel) - THERMAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A647677'],
            ['part_number' => 'A647677', 'finish' => 'DB', 'description' => 'Door Stile (Bevel) - THERMAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A647677'],
            ['part_number' => 'A647879', 'finish' => 'BL', 'description' => 'Door Stile (Bevel) - THERMAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A647879'],
            ['part_number' => 'A647879', 'finish' => 'C2', 'description' => 'Door Stile (Bevel) - THERMAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A647879'],
            ['part_number' => 'A647879', 'finish' => 'DB', 'description' => 'Door Stile (Bevel) - THERMAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A647879'],
            ['part_number' => 'A648181', 'finish' => 'BL', 'description' => 'Door Inactive Meeting Stile - THERMAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A648181'],
            ['part_number' => 'A648181', 'finish' => 'C2', 'description' => 'Door Inactive Meeting Stile - THERMAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A648181'],
            ['part_number' => 'A648181', 'finish' => 'DB', 'description' => 'Door Inactive Meeting Stile - THERMAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A648181'],
            ['part_number' => 'A648282', 'finish' => 'BL', 'description' => 'Door Inactive Meeting Stile - THERMAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A648282'],
            ['part_number' => 'A648282', 'finish' => 'C2', 'description' => 'Door Inactive Meeting Stile - THERMAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A648282'],
            ['part_number' => 'A648282', 'finish' => 'DB', 'description' => 'Door Inactive Meeting Stile - THERMAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A648282'],
            ['part_number' => 'A648383', 'finish' => 'BL', 'description' => 'Door Stile (Center Pivot) - THERMAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A648383'],
            ['part_number' => 'A648383', 'finish' => 'C2', 'description' => 'Door Stile (Center Pivot) - THERMAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A648383'],
            ['part_number' => 'A648383', 'finish' => 'DB', 'description' => 'Door Stile (Center Pivot) - THERMAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A648383'],
            ['part_number' => 'A648686', 'finish' => 'BL', 'description' => 'Door Stile (Center Pivot) - THERMAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A648686'],
            ['part_number' => 'A648686', 'finish' => 'C2', 'description' => 'Door Stile (Center Pivot) - THERMAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A648686'],
            ['part_number' => 'A648686', 'finish' => 'DB', 'description' => 'Door Stile (Center Pivot) - THERMAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A648686'],
            ['part_number' => 'A648787', 'finish' => 'BL', 'description' => 'Door Inactive Meeting Stile - THERMAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A648787'],
            ['part_number' => 'A648787', 'finish' => 'C2', 'description' => 'Door Inactive Meeting Stile - THERMAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A648787'],
            ['part_number' => 'A648787', 'finish' => 'DB', 'description' => 'Door Inactive Meeting Stile - THERMAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A648787'],
            ['part_number' => 'A648889', 'finish' => 'BL', 'description' => 'Door Stile (Bevel) - THERMAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A648889'],
            ['part_number' => 'A648889', 'finish' => 'C2', 'description' => 'Door Stile (Bevel) - THERMAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A648889'],
            ['part_number' => 'A648889', 'finish' => 'DB', 'description' => 'Door Stile (Bevel) - THERMAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A648889'],
            ['part_number' => 'A649090', 'finish' => 'BL', 'description' => 'Door Stile (Center Pivot) - THERMAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A649090'],
            ['part_number' => 'A649090', 'finish' => 'C2', 'description' => 'Door Stile (Center Pivot) - THERMAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A649090'],
            ['part_number' => 'A649090', 'finish' => 'DB', 'description' => 'Door Stile (Center Pivot) - THERMAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => '*A649090'],
            ['part_number' => 'E1537', 'finish' => 'BL', 'description' => 'Door Rail 3" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1537', 'finish' => 'C2', 'description' => 'Door Rail 3" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1537', 'finish' => 'DB', 'description' => 'Door Rail 3" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1544', 'finish' => 'BL', 'description' => 'Door Rail 5" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1544', 'finish' => 'C2', 'description' => 'Door Rail 5" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1544', 'finish' => 'DB', 'description' => 'Door Rail 5" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1547', 'finish' => 'BL', 'description' => 'Door Stile (Bevel) - MONUMENTAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1547', 'finish' => 'C2', 'description' => 'Door Stile (Bevel) - MONUMENTAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1547', 'finish' => 'DB', 'description' => 'Door Stile (Bevel) - MONUMENTAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1548', 'finish' => 'BL', 'description' => 'Door Inactive Meeting Stile - MONUMENTAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1548', 'finish' => 'C2', 'description' => 'Door Inactive Meeting Stile - MONUMENTAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1548', 'finish' => 'DB', 'description' => 'Door Inactive Meeting Stile - MONUMENTAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1549', 'finish' => 'BL', 'description' => 'Door Stile (Center Pivot) - MONUMENTAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1549', 'finish' => 'C2', 'description' => 'Door Stile (Center Pivot) - MONUMENTAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1549', 'finish' => 'DB', 'description' => 'Door Stile (Center Pivot) - MONUMENTAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1550', 'finish' => 'BL', 'description' => 'Door Rail 3 1/2" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1550', 'finish' => 'C2', 'description' => 'Door Rail 3 1/2" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1550', 'finish' => 'DB', 'description' => 'Door Rail 3 1/2" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1551', 'finish' => 'BL', 'description' => 'Door Rail 6" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1551', 'finish' => 'C2', 'description' => 'Door Rail 6" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1551', 'finish' => 'DB', 'description' => 'Door Rail 6" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1649', 'finish' => 'BL', 'description' => 'Door Astragal Stile - STANDARD NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1649', 'finish' => 'C2', 'description' => 'Door Astragal Stile - STANDARD NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1649', 'finish' => 'DB', 'description' => 'Door Astragal Stile - STANDARD NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1650', 'finish' => 'BL', 'description' => 'Door Astragal Stile - STANDARD MEDIUM STILE 4in.', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1650', 'finish' => 'C2', 'description' => 'Door Astragal Stile - STANDARD MEDIUM STILE 4in.', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1650', 'finish' => 'DB', 'description' => 'Door Astragal Stile - STANDARD MEDIUM STILE 4in.', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1653', 'finish' => 'BL', 'description' => 'Door Astragal Stile - MONUMENTAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1653', 'finish' => 'C2', 'description' => 'Door Astragal Stile - MONUMENTAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1653', 'finish' => 'DB', 'description' => 'Door Astragal Stile - MONUMENTAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1654', 'finish' => 'BL', 'description' => 'Door Astragal Stile - MONUMENTAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1654', 'finish' => 'C2', 'description' => 'Door Astragal Stile - MONUMENTAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1654', 'finish' => 'DB', 'description' => 'Door Astragal Stile - MONUMENTAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1655', 'finish' => 'BL', 'description' => 'Door Astragal Stile - MONUMENTAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1655', 'finish' => 'C2', 'description' => 'Door Astragal Stile - MONUMENTAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1655', 'finish' => 'DB', 'description' => 'Door Astragal Stile - MONUMENTAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1897', 'finish' => 'BL', 'description' => 'Door Stile (Bevel) - MONUMENTAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1897', 'finish' => 'C2', 'description' => 'Door Stile (Bevel) - MONUMENTAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E1897', 'finish' => 'DB', 'description' => 'Door Stile (Bevel) - MONUMENTAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E2056', 'finish' => 'BL', 'description' => 'Door Rail 9" (stacked) - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E2056', 'finish' => 'C2', 'description' => 'Door Rail 9" (stacked) - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E2056', 'finish' => 'DB', 'description' => 'Door Rail 9" (stacked) - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E5855', 'finish' => 'BL', 'description' => 'Door Rail 2 3/8" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E5855', 'finish' => 'C2', 'description' => 'Door Rail 2 3/8" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E5855', 'finish' => 'DB', 'description' => 'Door Rail 2 3/8" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6002', 'finish' => 'BL', 'description' => 'Door Rail 6 3/8" (mid-panel) - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6002', 'finish' => 'C2', 'description' => 'Door Rail 6 3/8" (mid-panel) - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6002', 'finish' => 'DB', 'description' => 'Door Rail 6 3/8" (mid-panel) - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6182', 'finish' => 'BL', 'description' => 'Door Rail 7 1/2" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6182', 'finish' => 'C2', 'description' => 'Door Rail 7 1/2" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6182', 'finish' => 'DB', 'description' => 'Door Rail 7 1/2" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6189', 'finish' => 'BL', 'description' => 'Door Rail 7 1/2" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6189', 'finish' => 'C2', 'description' => 'Door Rail 7 1/2" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6189', 'finish' => 'DB', 'description' => 'Door Rail 7 1/2" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6437', 'finish' => 'BL', 'description' => 'Door Stile (Bevel) - MONUMENTAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6437', 'finish' => 'C2', 'description' => 'Door Stile (Bevel) - MONUMENTAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6437', 'finish' => 'DB', 'description' => 'Door Stile (Bevel) - MONUMENTAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6438', 'finish' => 'BL', 'description' => 'Door Inactive Meeting Stile - MONUMENTAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6438', 'finish' => 'C2', 'description' => 'Door Inactive Meeting Stile - MONUMENTAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6438', 'finish' => 'DB', 'description' => 'Door Inactive Meeting Stile - MONUMENTAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6441', 'finish' => 'BL', 'description' => 'Door Stile (Center Pivot) - MONUMENTAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6441', 'finish' => 'C2', 'description' => 'Door Stile (Center Pivot) - MONUMENTAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6441', 'finish' => 'DB', 'description' => 'Door Stile (Center Pivot) - MONUMENTAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6443', 'finish' => 'BL', 'description' => 'Door Rail 3 1/2" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6443', 'finish' => 'C2', 'description' => 'Door Rail 3 1/2" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6443', 'finish' => 'DB', 'description' => 'Door Rail 3 1/2" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6444', 'finish' => 'BL', 'description' => 'Door Rail 4 1/2" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6444', 'finish' => 'C2', 'description' => 'Door Rail 4 1/2" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6444', 'finish' => 'DB', 'description' => 'Door Rail 4 1/2" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6445', 'finish' => 'BL', 'description' => 'Door Rail 12" (stacked) - Monumental (Stacked)', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6445', 'finish' => 'C2', 'description' => 'Door Rail 12" (stacked) - Monumental (Stacked)', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6445', 'finish' => 'DB', 'description' => 'Door Rail 12" (stacked) - Monumental (Stacked)', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6446', 'finish' => 'BL', 'description' => 'Door Rail 20" (stacked) - Monumental (Stacked)', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6446', 'finish' => 'C2', 'description' => 'Door Rail 20" (stacked) - Monumental (Stacked)', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6446', 'finish' => 'DB', 'description' => 'Door Rail 20" (stacked) - Monumental (Stacked)', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6454', 'finish' => 'BL', 'description' => 'Door Rail 10" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6454', 'finish' => 'C2', 'description' => 'Door Rail 10" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6454', 'finish' => 'DB', 'description' => 'Door Rail 10" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6616', 'finish' => 'BL', 'description' => 'Door Rail 6" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6616', 'finish' => 'C2', 'description' => 'Door Rail 6" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6616', 'finish' => 'DB', 'description' => 'Door Rail 6" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6627', 'finish' => 'BL', 'description' => 'Door Inactive Meeting Stile - MONUMENTAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6627', 'finish' => 'C2', 'description' => 'Door Inactive Meeting Stile - MONUMENTAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6627', 'finish' => 'DB', 'description' => 'Door Inactive Meeting Stile - MONUMENTAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6628', 'finish' => 'BL', 'description' => 'Door Stile (Center Pivot) - MONUMENTAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6628', 'finish' => 'C2', 'description' => 'Door Stile (Center Pivot) - MONUMENTAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6628', 'finish' => 'DB', 'description' => 'Door Stile (Center Pivot) - MONUMENTAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6629', 'finish' => 'BL', 'description' => 'Door Rail 4 1/2" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6629', 'finish' => 'C2', 'description' => 'Door Rail 4 1/2" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6629', 'finish' => 'DB', 'description' => 'Door Rail 4 1/2" - Monumental', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6844', 'finish' => 'BL', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - MONUMENTAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6844', 'finish' => 'C2', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - MONUMENTAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6844', 'finish' => 'DB', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - MONUMENTAL NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6845', 'finish' => 'BL', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - MONUMENTAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6845', 'finish' => 'C2', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - MONUMENTAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6845', 'finish' => 'DB', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - MONUMENTAL MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6846', 'finish' => 'BL', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - MONUMENTAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6846', 'finish' => 'C2', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - MONUMENTAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6846', 'finish' => 'DB', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - MONUMENTAL WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6855', 'finish' => 'BL', 'description' => 'Door Rail 2 1/8" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6855', 'finish' => 'C2', 'description' => 'Door Rail 2 1/8" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6855', 'finish' => 'DB', 'description' => 'Door Rail 2 1/8" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6856', 'finish' => 'BL', 'description' => 'Door Rail 4" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6856', 'finish' => 'C2', 'description' => 'Door Rail 4" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6856', 'finish' => 'DB', 'description' => 'Door Rail 4" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6858', 'finish' => 'BL', 'description' => 'Door Rail 5" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6858', 'finish' => 'C2', 'description' => 'Door Rail 5" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6858', 'finish' => 'DB', 'description' => 'Door Rail 5" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6859', 'finish' => 'BL', 'description' => 'Door Rail 3" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6859', 'finish' => 'C2', 'description' => 'Door Rail 3" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6859', 'finish' => 'DB', 'description' => 'Door Rail 3" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6861', 'finish' => 'BL', 'description' => 'Door Rail 4 1/2" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6861', 'finish' => 'C2', 'description' => 'Door Rail 4 1/2" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6861', 'finish' => 'DB', 'description' => 'Door Rail 4 1/2" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6862', 'finish' => 'BL', 'description' => 'Door Rail 12" (stacked) - Standard (Stacked)', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6862', 'finish' => 'C2', 'description' => 'Door Rail 12" (stacked) - Standard (Stacked)', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6862', 'finish' => 'DB', 'description' => 'Door Rail 12" (stacked) - Standard (Stacked)', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6863', 'finish' => 'BL', 'description' => 'Door Rail 7 1/2" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6863', 'finish' => 'C2', 'description' => 'Door Rail 7 1/2" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6863', 'finish' => 'DB', 'description' => 'Door Rail 7 1/2" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6865', 'finish' => 'BL', 'description' => 'Door Rail 20" (stacked) - Standard (Stacked)', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6865', 'finish' => 'C2', 'description' => 'Door Rail 20" (stacked) - Standard (Stacked)', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6865', 'finish' => 'DB', 'description' => 'Door Rail 20" (stacked) - Standard (Stacked)', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6866', 'finish' => 'BL', 'description' => 'Door Rail 6 1/2" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6866', 'finish' => 'C2', 'description' => 'Door Rail 6 1/2" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E6866', 'finish' => 'DB', 'description' => 'Door Rail 6 1/2" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7054', 'finish' => 'BL', 'description' => 'Door Rail 4" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7054', 'finish' => 'C2', 'description' => 'Door Rail 4" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7054', 'finish' => 'DB', 'description' => 'Door Rail 4" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7055', 'finish' => 'BL', 'description' => 'Door Stile (Bevel) - STANDARD NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7055', 'finish' => 'C2', 'description' => 'Door Stile (Bevel) - STANDARD NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7055', 'finish' => 'DB', 'description' => 'Door Stile (Bevel) - STANDARD NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7056', 'finish' => 'BL', 'description' => 'Door Inactive Meeting Stile - STANDARD NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7056', 'finish' => 'C2', 'description' => 'Door Inactive Meeting Stile - STANDARD NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7056', 'finish' => 'DB', 'description' => 'Door Inactive Meeting Stile - STANDARD NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7057', 'finish' => 'BL', 'description' => 'Door Stile (Center Pivot) - STANDARD NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7057', 'finish' => 'C2', 'description' => 'Door Stile (Center Pivot) - STANDARD NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7057', 'finish' => 'DB', 'description' => 'Door Stile (Center Pivot) - STANDARD NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7059', 'finish' => 'BL', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - STANDARD NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7059', 'finish' => 'C2', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - STANDARD NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7059', 'finish' => 'DB', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - STANDARD NARROW STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7068', 'finish' => 'BL', 'description' => 'Door Stile (Center Pivot) - STANDARD MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7068', 'finish' => 'C2', 'description' => 'Door Stile (Center Pivot) - STANDARD MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7068', 'finish' => 'DB', 'description' => 'Door Stile (Center Pivot) - STANDARD MEDIUM STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7085', 'finish' => 'BL', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - STANDARD MEDIUM STILE 4in.', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7085', 'finish' => 'C2', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - STANDARD MEDIUM STILE 4in.', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7085', 'finish' => 'DB', 'description' => 'Door Stile (Rabbet/Continuous Hinge) - STANDARD MEDIUM STILE 4in.', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7086', 'finish' => 'BL', 'description' => 'Door Stile (Bevel) - STANDARD MEDIUM STILE 4in.', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7086', 'finish' => 'C2', 'description' => 'Door Stile (Bevel) - STANDARD MEDIUM STILE 4in.', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7086', 'finish' => 'DB', 'description' => 'Door Stile (Bevel) - STANDARD MEDIUM STILE 4in.', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7087', 'finish' => 'BL', 'description' => 'Door Inactive Meeting Stile - STANDARD MEDIUM STILE 4in.', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7087', 'finish' => 'C2', 'description' => 'Door Inactive Meeting Stile - STANDARD MEDIUM STILE 4in.', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7087', 'finish' => 'DB', 'description' => 'Door Inactive Meeting Stile - STANDARD MEDIUM STILE 4in.', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7088', 'finish' => 'BL', 'description' => 'Door Stile (Center Pivot) - STANDARD MEDIUM STILE 4in.', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7088', 'finish' => 'C2', 'description' => 'Door Stile (Center Pivot) - STANDARD MEDIUM STILE 4in.', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7088', 'finish' => 'DB', 'description' => 'Door Stile (Center Pivot) - STANDARD MEDIUM STILE 4in.', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7140', 'finish' => 'BL', 'description' => 'Door Rail 4 1/2" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7140', 'finish' => 'C2', 'description' => 'Door Rail 4 1/2" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7140', 'finish' => 'DB', 'description' => 'Door Rail 4 1/2" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7148', 'finish' => 'BL', 'description' => 'Door Rail 3" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7148', 'finish' => 'C2', 'description' => 'Door Rail 3" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7148', 'finish' => 'DB', 'description' => 'Door Rail 3" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7248', 'finish' => 'BL', 'description' => 'Door Rail 1 9/32" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7248', 'finish' => 'C2', 'description' => 'Door Rail 1 9/32" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7248', 'finish' => 'DB', 'description' => 'Door Rail 1 9/32" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7255', 'finish' => 'BL', 'description' => 'Door Rail 2 1/8" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7255', 'finish' => 'C2', 'description' => 'Door Rail 2 1/8" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7255', 'finish' => 'DB', 'description' => 'Door Rail 2 1/8" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7418', 'finish' => 'BL', 'description' => 'Door Stile (Center Pivot) - STANDARD WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7418', 'finish' => 'C2', 'description' => 'Door Stile (Center Pivot) - STANDARD WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7418', 'finish' => 'DB', 'description' => 'Door Stile (Center Pivot) - STANDARD WIDE STILE', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7419', 'finish' => 'BL', 'description' => 'Door Rail 6 1/2" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7419', 'finish' => 'C2', 'description' => 'Door Rail 6 1/2" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7419', 'finish' => 'DB', 'description' => 'Door Rail 6 1/2" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7459', 'finish' => 'BL', 'description' => 'Door Rail 3/4" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7459', 'finish' => 'C2', 'description' => 'Door Rail 3/4" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7459', 'finish' => 'DB', 'description' => 'Door Rail 3/4" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7734', 'finish' => 'BL', 'description' => 'Door Rail 1 3/4" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7734', 'finish' => 'C2', 'description' => 'Door Rail 1 3/4" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'E7734', 'finish' => 'DB', 'description' => 'Door Rail 1 3/4" - Standard', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P0142D10', 'finish' => '0R', 'description' => 'Mid Rail Lug for E6865', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P0142D175', 'finish' => '0R', 'description' => 'Mid Rail Lug for E7734', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P0142D2125', 'finish' => '0R', 'description' => 'Mid Rail Lug for E6855', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P0142D3', 'finish' => '0R', 'description' => 'Mid Rail Lug for E6859', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P0142D4', 'finish' => '0R', 'description' => 'Mid Rail Lug for E6856', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P0142D45', 'finish' => '0R', 'description' => 'Mid Rail Lug for E6861', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P0142D5', 'finish' => '0R', 'description' => 'Mid Rail Lug for E6858', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P0142D6', 'finish' => '0R', 'description' => 'Mid Rail Lug for E6862', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P0142D65', 'finish' => '0R', 'description' => 'Mid Rail Lug for E6866', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P0142D75', 'finish' => '0R', 'description' => 'Mid Rail Lug for E6863', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P0142M10', 'finish' => '0R', 'description' => 'Mid Rail Lug for E6446', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P0142M2375', 'finish' => '0R', 'description' => 'Mid Rail Lug for E5855', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P0142M35', 'finish' => '0R', 'description' => 'Mid Rail Lug for E6443', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P0142M45', 'finish' => '0R', 'description' => 'Mid Rail Lug for E6444', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P0142M6', 'finish' => '0R', 'description' => 'Mid Rail Lug for E6445', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P0142T3', 'finish' => '0R', 'description' => 'Mid Rail Lug for A643030', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P0142T4', 'finish' => '0R', 'description' => 'Mid Rail Lug for A646464', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P022A', 'finish' => '0R', 'description' => 'Tie Rod - NARROW STILE (24"-30")', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P022B', 'finish' => '0R', 'description' => 'Tie Rod - NARROW STILE (30"-36")', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P022C', 'finish' => '0R', 'description' => 'Tie Rod - NARROW STILE (36"-42")', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P022D', 'finish' => '0R', 'description' => 'Tie Rod - NARROW STILE (38"-44")', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P022E', 'finish' => '0R', 'description' => 'Tie Rod - NARROW STILE (42"-48")', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P022F', 'finish' => '0R', 'description' => 'Tie Rod - MEDIUM STILE (24"-30")', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P022I', 'finish' => '0R', 'description' => 'Tie Rod - MEDIUM STILE (42"-48")', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P022J', 'finish' => '0R', 'description' => 'Tie Rod - MEDIUM STILE 4in. (24"-30")', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P022K', 'finish' => '0R', 'description' => 'Tie Rod - MEDIUM STILE 4in. (30"-36")', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P022L', 'finish' => '0R', 'description' => 'Tie Rod - MEDIUM STILE 4in. (36"-42")', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P022M', 'finish' => '0R', 'description' => 'Tie Rod - MEDIUM STILE 4in. (38"-44")', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P022N', 'finish' => '0R', 'description' => 'Tie Rod - MEDIUM STILE 4in. (42"-48")', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P022O', 'finish' => '0R', 'description' => 'Tie Rod - WIDE STILE (24"-30")', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P022Q', 'finish' => '0R', 'description' => 'Tie Rod - WIDE STILE (36"-42")', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P022R', 'finish' => '0R', 'description' => 'Tie Rod - WIDE STILE (42"-48")', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P022S', 'finish' => '0R', 'description' => 'Tie Rod - MONUMENTAL NARROW STILE (24"-30")', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P022T', 'finish' => '0R', 'description' => 'Tie Rod - MONUMENTAL NARROW STILE (30"-36")', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P022U', 'finish' => '0R', 'description' => 'Tie Rod - MONUMENTAL NARROW STILE (42"-48")', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P022V', 'finish' => '0R', 'description' => 'Tie Rod -  (0"-9999")', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P046T10', 'finish' => '0R', 'description' => 'Rail Lug for A641010', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P046T25', 'finish' => '0R', 'description' => 'Rail Lug for A642525', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P046T4', 'finish' => '0R', 'description' => 'Rail Lug for A641414', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P046T5', 'finish' => '0R', 'description' => 'Rail Lug for A641515', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P052D2125', 'finish' => '0R', 'description' => 'Rail Lug for E7255', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P052D3', 'finish' => '0R', 'description' => 'Rail Lug for E7148', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P052D4', 'finish' => '0R', 'description' => 'Rail Lug for E7054', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P052D45', 'finish' => '0R', 'description' => 'Rail Lug for E7140', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P052D6', 'finish' => '0R', 'description' => 'Rail Lug for E6616', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P052D65', 'finish' => '0R', 'description' => 'Rail Lug for E7419', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P052D75', 'finish' => '0R', 'description' => 'Rail Lug for E6182', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P052M10', 'finish' => '0R', 'description' => 'Rail Lug for E6454', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P052M3', 'finish' => '0R', 'description' => 'Rail Lug for E1537', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P052M35', 'finish' => '0R', 'description' => 'Rail Lug for E1550', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P052M45', 'finish' => '0R', 'description' => 'Rail Lug for E6629', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P052M5', 'finish' => '0R', 'description' => 'Rail Lug for E1544', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P052M6', 'finish' => '0R', 'description' => 'Rail Lug for E1551', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P052M75', 'finish' => '0R', 'description' => 'Rail Lug for E6189', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P1401', 'finish' => '0R', 'description' => 'Mid Rail Lug for E7459', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P1431', 'finish' => '0R', 'description' => 'Glass Gasket - 5/16"', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P1928A', 'finish' => '0R', 'description' => 'Setting Block Kit - STANDARD 3/16"', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P1928B', 'finish' => '0R', 'description' => 'Setting Block Kit - STANDARD 3/8"', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P1928C', 'finish' => '0R', 'description' => 'Setting Block Kit - STANDARD 1/2"', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P1928D', 'finish' => '0R', 'description' => 'Setting Block Kit #2 - STANDARD 3/16"', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P1928E', 'finish' => '0R', 'description' => 'Setting Block Kit #2 - STANDARD 3/8"', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P1928F', 'finish' => '0R', 'description' => 'Setting Block Kit - THERMAL 1/2"', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P1928G', 'finish' => '0R', 'description' => 'Setting Block Kit #2 - THERMAL 1/2"', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P2728', 'finish' => '0R', 'description' => 'Standard Storefront Gasket', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P597', 'finish' => '0R', 'description' => 'Mid Rail Lug for E2056', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P795DT', 'finish' => 'C2', 'description' => 'Top Offset Pivot - Door Portion', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P795DT', 'finish' => 'DB', 'description' => 'Top Offset Pivot - Door Portion', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P795H', 'finish' => 'C2', 'description' => 'Top Offset Pivot - Frame Portion', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'P795H', 'finish' => 'DB', 'description' => 'Top Offset Pivot - Frame Portion', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'S166', 'finish' => '0R', 'description' => '1/4-20 x 1" FHMS', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'S197', 'finish' => '0R', 'description' => 'Mid Rail Lug Fastener #1 for E7459', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'S206', 'finish' => '0R', 'description' => 'Mid Rail Lug Fastener #2 for E5855', 'manufacturer' => null, 'manufacturer_part_number' => null],
            ['part_number' => 'S270', 'finish' => '0R', 'description' => 'Mid Rail Lug Fastener #2 for A643030', 'manufacturer' => null, 'manufacturer_part_number' => null],        ];

        $now = now();

        foreach ($rows as $row) {
            $existing = DB::table('products')
                ->where('part_number', $row['part_number'])
                ->where('finish', $row['finish'])
                ->first();

            if ($existing) {
                continue;
            }

            $sku = $row['finish']
                ? strtoupper($row['part_number'].'-'.$row['finish'])
                : strtoupper($row['part_number']);

            DB::table('products')->insert([
                'sku' => $sku,
                'part_number' => $row['part_number'],
                'finish' => $row['finish'],
                'description' => $row['description'],
                'manufacturer' => $row['manufacturer'],
                'manufacturer_part_number' => $row['manufacturer_part_number'],
                'unit_cost' => 0,
                'quantity_on_hand' => 0,
                'quantity_committed' => 0,
                'supplier_id' => $supplierId,
                'is_special_order' => true,
                'nonsof' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // ASA strike: not a stocked/sold part — it comes bundled with its
        // parent lockset/panic hardware. Every other item in the hwlib
        // "Strike" category has pn = NULL for the same reason; align ASA
        // with that pattern and drop its placeholder Product if present.
        $asaItem = DB::table('configurator_hwlib_items')->where('pn', 'ASA')->first();
        if ($asaItem) {
            DB::table('configurator_hwlib_items')->where('id', $asaItem->id)->update([
                'pn' => null,
                'updated_at' => $now,
            ]);
        }
        DB::table('products')
            ->where('part_number', 'ASA')
            ->whereNull('finish')
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);

        // P1421 (Adams Rite 4510 Deadlatch): a handed item. P1421L/P1421R
        // already exist as the real orderable SKUs, so mark the hwlib
        // catalog item handed and clear its single pn, then drop the
        // ambiguous generic P1421 placeholder if present.
        $p1421Item = DB::table('configurator_hwlib_items')->where('pn', 'P1421')->first();
        if ($p1421Item) {
            DB::table('configurator_hwlib_items')->where('id', $p1421Item->id)->update([
                'handed' => true,
                'pn' => null,
                'updated_at' => $now,
            ]);
        }
        DB::table('products')
            ->where('part_number', 'P1421')
            ->whereNull('finish')
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        // Not reversible in general: some of these part_number/finish rows
        // may coincide with real, independently-created inventory. Data-only
        // seed migration — intentionally left as a no-op.
    }
};
