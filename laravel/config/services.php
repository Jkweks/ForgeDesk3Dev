<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'tiger_bridge' => [
        // The small Node service (separate repo, not part of this app) that
        // owns the TigerStop's serial port and the Zebra printer's socket —
        // runs on the shop tablet itself.
        'url' => env('TIGER_BRIDGE_URL', 'http://127.0.0.1:9111'),

        // Shared secret the bridge requires on every request (see
        // tiger-bridge/.env.example's BRIDGE_TOKEN) so only ForgeDesk can
        // move the saw or trigger the printer — not just anyone on the LAN.
        'token' => env('TIGER_BRIDGE_TOKEN'),

        // No tiger-bridge reachable from here (e.g. local dev away from the
        // shop). When true, App\Services\CutFlow\TigerBridgeClient simulates
        // every response instead of making an HTTP call.
        'fake' => env('TIGER_BRIDGE_FAKE', false),
    ],

];
