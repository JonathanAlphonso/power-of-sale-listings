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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // IDX / PropTx credentials
    // Note: Our IDX integration uses the PropTx RESO OData API. The `IDX_*`
    // env vars map directly to PropTx values (bearer token + base URL).
    // If PropTx rotates credentials, update these env vars.
    'idx' => [
        'base_uri' => env('IDX_BASE_URI'),
        'token' => env('IDX_TOKEN'),
        'run_live_tests' => env('RUN_LIVE_IDX_TESTS', false),
        // Long-running, full API import tests. Only run these when explicitly enabled.
        'run_long_live_tests' => env('RUN_LONG_LIVE_IDX_TESTS', false),
        // When true, the homepage will fall back to showing StandardStatus=Active
        // listings if the PoS (Power of Sale) query returns no results.
        'homepage_fallback_to_active' => env('IDX_HOMEPAGE_FALLBACK_ACTIVE', true),
    ],

    // VOW / PropTx credentials (password-protected feed)
    // Mirrors IDX but may return additional fields. Must not be publicly displayed.
    'vow' => [
        // If VOW_BASE_URI is not set, default to IDX_BASE_URI since both feeds share the same host.
        'base_uri' => env('VOW_BASE_URI', env('IDX_BASE_URI')),
        'token' => env('VOW_TOKEN'),
    ],

    // MapTiler - used for listing maps
    // Register at maptiler.com for a free API key (100k map loads/month free)
    'maptiler' => [
        'key' => env('MAPTILER_API_KEY'),
    ],

];
