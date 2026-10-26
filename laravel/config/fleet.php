<?php

return [
    /*
    | Field-app sync. ForgeDesk stays on the local network and always initiates the
    | connection: it publishes a trimmed asset/checklist snapshot to the field app's
    | database and pulls submissions from its inbox. Disabled until the field DB exists.
    | Contract: docs/fleet-field-sync-contract.md
    */
    'sync' => [
        'enabled' => env('FIELDSYNC_ENABLED', false),
        'connection' => 'fieldsync',
        'stale_after_minutes' => 15,
        'batch_size' => 100,
        'max_photo_bytes' => 10 * 1024 * 1024,
        'max_clock_skew_days' => 14, // device timestamps further than this from now are clamped
        'purge_ingested_photos' => env('FIELDSYNC_PURGE_PHOTOS', true),
    ],

    // Compliance/document alert thresholds, in days before expiry.
    'alert_thresholds' => [90, 60, 30, 7],
];
