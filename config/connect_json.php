<?php

return [
    'log' => [
        'level' => 'warning',
    ],
    'outbounds' => [
        'proxy_tag' => 'proxy',
        'direct_tag' => 'direct',
        'block_tag' => 'block',
    ],
    'inbounds' => [
        [
            'listen' => '127.0.0.1',
            'port' => 10808,
            'protocol' => 'socks',
            'settings' => [
                'udp' => true,
            ],
            'sniffing' => [
                'destOverride' => ['http', 'tls'],
                'enabled' => true,
                'routeOnly' => false,
            ],
            'tag' => 'socks',
        ],
        [
            'listen' => '127.0.0.1',
            'port' => 10809,
            'protocol' => 'http',
            'settings' => new stdClass(),
            'sniffing' => [
                'destOverride' => ['http', 'tls'],
                'enabled' => true,
                'routeOnly' => false,
            ],
            'tag' => 'http',
        ],
    ],
    'dns' => [
        'query_strategy' => 'UseIPv4',
        'servers' => [
            [
                'address' => '8.8.8.8',
                'domains' => [
                    'domain:openai.com',
                    'domain:chatgpt.com',
                    'domain:codex.com',
                    'domain:oaistatic.com',
                    'domain:oaiusercontent.com',
                ],
                'skipFallback' => true,
            ],
            [
                'address' => '77.88.8.8',
                'domains' => [
                    'geosite:category-ru',
                ],
                'skipFallback' => true,
            ],
            '8.8.8.8',
        ],
    ],
    'routing' => [
        'domain_strategy' => 'AsIs',
        'rules' => [
            [
                'type' => 'field',
                'port' => '25,143,465,587,993,995,2525,3389',
                'outboundTag' => 'direct',
            ],
            [
                'type' => 'field',
                'domain' => [
                    'domain:wb.ru',
                    'domain:wildberries.ru',
                    'domain:dixy.ru',
                ],
                'outboundTag' => 'proxy',
            ],
            [
                'type' => 'field',
                'domain' => [
                    'domain:localhost',
                    'domain:local',
                    'geosite:category-ru',
                ],
                'outboundTag' => 'direct',
            ],
            [
                'type' => 'field',
                'domain' => [
                    'domain:com',
                    'domain:org',
                    'domain:net',
                    'domain:io',
                    'domain:ai',
                ],
                'outboundTag' => 'proxy',
            ],
            [
                'type' => 'field',
                'ip' => [
                    'geoip:private',
                    '31.130.140.0/22',
                    '46.226.122.0/24',
                    '85.158.185.0/24',
                    '91.212.64.0/24',
                    '91.223.93.0/24',
                    '185.73.192.0/22',
                    '194.9.208.0/22',
                    '195.34.20.0/23',
                ],
                'outboundTag' => 'direct',
            ],
            [
                'type' => 'field',
                'network' => 'tcp,udp',
                'outboundTag' => 'proxy',
            ],
        ],
    ],
];
