<?php

use Telegram\Bot\Commands\HelpCommand;

return [
    'bots' => [
        'mybot' => [
            'token' => env('TELEGRAM_BOT_TOKEN'),
            'certificate_path' => env('TELEGRAM_CERTIFICATE_PATH'),
            'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
            'allowed_updates' => null,
            'commands' => [],
        ],
    ],

    'default' => 'mybot',

    'async_requests' => env('TELEGRAM_ASYNC_REQUESTS', false),

    'http_client_handler' => null,

    'base_bot_url' => null,

    'resolve_command_dependencies' => true,

    'commands' => [
        HelpCommand::class,
    ],

    'command_groups' => [],

    'shared_commands' => [],

    'proxy' => env('TELEGRAM_PROXY'),
];
