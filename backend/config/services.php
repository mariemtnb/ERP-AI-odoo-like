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

    // Online payment provider. 'mock' is a built-in sandbox that moves no real
    // money; set 'konnect' or 'flouci' with the credentials below to go live.
    'payments' => [
        'provider' => env('PAYMENT_PROVIDER', 'mock'),
        'konnect' => [
            'base_url' => env('KONNECT_BASE_URL', 'https://api.konnect.network/api/v2'),
            'api_key' => env('KONNECT_API_KEY'),
            'wallet_id' => env('KONNECT_WALLET_ID'),
        ],
        'flouci' => [
            'base_url' => env('FLOUCI_BASE_URL', 'https://developers.flouci.com/api'),
            'app_token' => env('FLOUCI_APP_TOKEN'),
            'app_secret' => env('FLOUCI_APP_SECRET'),
        ],
    ],

    // Electronic-invoicing provider. 'mock' is a built-in sandbox that submits
    // to nothing; set 'ttn' with the credentials below to file to TTN.
    'einvoice' => [
        'provider' => env('EINVOICE_PROVIDER', 'mock'),
        'ttn' => [
            'base_url' => env('TTN_BASE_URL', 'https://www.tunceps.com.tn/api'),
            'username' => env('TTN_USERNAME'),
            'password' => env('TTN_PASSWORD'),
            'api_key' => env('TTN_API_KEY'),
        ],
    ],

];
