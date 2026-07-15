<?php

return [
    'log' => [
        'level' => 'warn',
    ],
    'outbounds' => [
        'auto_tag' => 'Auto',
        'selector_tag' => 'Manual',
        'direct_tag' => 'direct',
        'block_tag' => 'block',
        'dns_tag' => 'dns-out',
    ],
    'dns' => [
        'strategy' => 'ipv4_only',
        'independent_cache' => true,
        'servers' => [
            [
                'tag' => 'dns-local',
                'address' => 'local',
                'detour' => 'direct',
            ],
            [
                'tag' => 'dns-remote',
                'address' => '1.1.1.1',
                'detour' => 'Manual',
            ],
            [
                'tag' => 'dns-google',
                'address' => '8.8.8.8',
                'detour' => 'Manual',
            ],
            [
                'tag' => 'dns-ru',
                'address' => '77.88.8.8',
                'detour' => 'direct',
            ],
        ],
        'rules' => [
            [
                'domain_suffix' => [
                    'openai.com',
                    'chatgpt.com',
                    'oaistatic.com',
                    'oaiusercontent.com',
                    'codex.com',
                ],
                'server' => 'dns-google',
            ],
            [
                'domain_suffix' => [
                    'localhost',
                    'local',
                    'ru',
                    'su',
                    'xn--p1ai',
                ],
                'server' => 'dns-ru',
            ],
        ],
        'final' => 'dns-remote',
    ],
    'route' => [
        'auto_detect_interface' => true,
        'final' => 'Manual',
        'rules' => [
            [
                'protocol' => 'dns',
                'outbound' => 'dns-out',
            ],
            [
                'port' => [53],
                'outbound' => 'dns-out',
            ],
            [
                'ip_is_private' => true,
                'outbound' => 'direct',
            ],
            [
                'domain_suffix' => [
                    'localhost',
                    'local',
                    'ru',
                    'su',
                    'xn--p1ai',
                ],
                'outbound' => 'direct',
            ],
            [
                'domain_suffix' => [
                    'openai.com',
                    'chatgpt.com',
                    'oaistatic.com',
                    'oaiusercontent.com',
                    'codex.com',
                ],
                'outbound' => 'Manual',
            ],
        ],
    ],
];
