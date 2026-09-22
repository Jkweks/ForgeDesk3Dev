<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Standard stock length
    |--------------------------------------------------------------------------
    |
    | The default raw-material length (in inches) used when projecting how
    | many additional full-length sticks are needed to finish a profile's
    | remaining cut list. Drops/remnants are handled separately: the operator
    | just types the drop's actual length into the "new stick" field.
    |
    */

    'standard_stock_length' => (float) env('CUTFLOW_STANDARD_STOCK_LENGTH', 288),

    /*
    |--------------------------------------------------------------------------
    | Saw kerf
    |--------------------------------------------------------------------------
    |
    | How much material the blade eats between two pieces cut from the same
    | stick, in inches.
    |
    */

    'kerf_inches' => (float) env('CUTFLOW_KERF_INCHES', 0.125),

];
