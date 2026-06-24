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

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'dev_chat_id' => env('TELEGRAM_DEV_CHAT_ID'),
        'mini_app_token_ttl_minutes' => env('TELEGRAM_MINI_APP_TOKEN_TTL_MINUTES', 43200),
        'mini_app_init_data_ttl_seconds' => env('TELEGRAM_MINI_APP_INIT_DATA_TTL_SECONDS', 3600),
    ],

    'yookassa' => [
        'shop_id' => env('YOO_KASSA_SHOP_ID'),
        'secret_key' => env('YOO_KASSA_SECRET_KEY'),
        'return_url' => env('YOO_KASSA_RETURN_URL', env('APP_URL')),
    ],
];
