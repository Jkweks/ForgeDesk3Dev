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

    /*
    |--------------------------------------------------------------------------
    | Cut-station tablet IP allowlist
    |--------------------------------------------------------------------------
    |
    | /cut-station is an intentionally unauthenticated kiosk route (see
    | routes/web.php) — anyone on the network can load it and see the cut
    | list. This does NOT gate that. It gates the handful of Dashboard
    | actions that actually move the saw or fire the printer
    | (App\Livewire\CutFlow\Dashboard::assertAuthorizedTablet()), so that
    | even though the page is visible network-wide, only the one shop-floor
    | tablet physically at the saw can make those specific actions succeed.
    |
    | Comma-separated IPs and/or IPv4 CIDR ranges, e.g.
    | "192.168.1.60" or "192.168.1.60/32". Left empty, this check is
    | skipped (every device can command the saw) — set it as soon as the
    | tablet's IP is known.
    |
    */

    'tablet_allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CUTFLOW_TABLET_ALLOWED_IPS', ''))
    ))),

];
