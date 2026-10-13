<?php

/**
 * Sill quarter-point report (App\Services\CutFlow\SillQuarterPointService). All values in inches.
 */
return [
    'left_gap' => 0.125,            // added left of the left jamb; shifts every sill position
    'right_gap' => 0.125,           // beyond the right jamb; receptor check only
    'vertical_width' => 2,          // female vertical width when the product has no dimension_width
    'default_jamb_width' => 2,      // jamb width when the product has no dimension_width and no entry below
    'comp_jamb_width' => 0,         // comp jambs have no width
    'receptor_gap' => 0.375,        // gap between pieces of a split sill receptor channel; receptor check only
    'fraction_denominator' => 64,   // fractions are rounded to the nearest 1/N and reduced
    'flag_tolerance' => 0.0625,     // receptor differences beyond this are flagged
    'knee_wall_tolerance' => 0.01,  // vertical-height difference that counts as a knee wall

    // Fallback widths by part number, used when the product has no dimension_width set.
    // Product data (Inventory > Configurator tab > Face Width) always wins.
    'part_widths' => [
        'T15356' => 2,    // same profile as the female vertical
        'T15361' => 4.5,  // confirmed against 5-E4, 6-E5, 7-E6, 13-E11
        'T15362' => 4.5,  // confirmed against 4-E3, 5-E4, 6-E5, 12-E10
    ],
];
