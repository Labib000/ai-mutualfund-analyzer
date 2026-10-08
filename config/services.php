<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'amfi' => [
        'nav_url' => env('AMFI_NAV_URL', 'https://www.amfiindia.com/spages/NAVAll.txt'),
        'timeout' => (int) env('AMFI_TIMEOUT_SECONDS', 60),
    ],

    'nav_history' => [
        'provider' => env('NAV_HISTORY_PROVIDER', 'mfapi'),
    ],

    'mfapi' => [
        'base_url' => env('MFAPI_BASE_URL', 'https://api.mfapi.in'),
        'timeout' => (int) env('MFAPI_TIMEOUT_SECONDS', 15),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
