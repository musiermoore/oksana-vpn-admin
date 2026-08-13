<?php

declare(strict_types=1);

use App\Enums\ExternalSubscriptionSourceFormat;

return [
    'keymat' => [
        'remote_enabled' => env('INCY_KEYMAT_REMOTE_ENABLED', true),
        'remote_url' => env(
            'INCY_KEYMAT_REMOTE_URL',
            'https://raw.githubusercontent.com/INCY-DEV/incy-link-encoder/main/src/keymat.ts'
        ),
        'cache_ttl_seconds' => (int) env('INCY_KEYMAT_CACHE_TTL_SECONDS', 86400),
        'request_timeout_seconds' => (int) env('INCY_KEYMAT_REQUEST_TIMEOUT_SECONDS', 10),
        'km_a_offset' => 1024,
        'km_b_offset' => 2048,
        'key_length' => 32,
        'salt' => 'incydeepcrypt1v2026.06',
        'expected_key_fingerprint' => 'b6bf708471cc90043232967660aade86a50b4e57929db2e53c5fa34db624c08c',
        'local_km_a_b64' => '7odqBjr3BNe0CfGRDZcxzBQZCB7AiZOgEnBVaHPh7y0=',
        'local_km_b_b64' => 'z1EN9DsquX6phHju5fGxz4DjpIT8k2kxbM2H5Ut5l7c=',
    ],
    'defaults' => [
        'source_format' => ExternalSubscriptionSourceFormat::Direct->value,
    ],
    'redirect' => [
        'request_timeout_seconds' => (int) env('INCY_REDIRECT_REQUEST_TIMEOUT_SECONDS', 20),
    ],
];
