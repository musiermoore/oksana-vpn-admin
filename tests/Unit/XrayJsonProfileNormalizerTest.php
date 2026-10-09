<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Subscriptions\XrayJsonProfileNormalizer;
use PHPUnit\Framework\TestCase;
use stdClass;

class XrayJsonProfileNormalizerTest extends TestCase
{
    public function test_it_normalizes_only_empty_http_inbound_settings_array(): void
    {
        $httpEmptyObjectSettings = new stdClass();
        $httpNonEmptySettings = ['accounts' => [['user' => 'alice', 'pass' => 'secret']]];
        $socksSettings = ['udp' => true];

        $profile = [
            'inbounds' => [
                [
                    'tag' => 'http-empty-array',
                    'port' => 10809,
                    'listen' => '127.0.0.1',
                    'protocol' => 'http',
                    'settings' => [],
                ],
                [
                    'tag' => 'http-empty-object',
                    'port' => 10810,
                    'listen' => '127.0.0.1',
                    'protocol' => 'http',
                    'settings' => $httpEmptyObjectSettings,
                ],
                [
                    'tag' => 'http-non-empty-object',
                    'port' => 10811,
                    'listen' => '127.0.0.1',
                    'protocol' => 'http',
                    'settings' => $httpNonEmptySettings,
                ],
                [
                    'tag' => 'socks',
                    'port' => 10808,
                    'listen' => '127.0.0.1',
                    'protocol' => 'socks',
                    'settings' => $socksSettings,
                ],
            ],
        ];

        $normalized = (new XrayJsonProfileNormalizer())->normalizeProfile($profile);

        $this->assertInstanceOf(stdClass::class, $normalized['inbounds'][0]['settings']);
        $this->assertSame([], get_object_vars($normalized['inbounds'][0]['settings']));
        $this->assertSame($httpEmptyObjectSettings, $normalized['inbounds'][1]['settings']);
        $this->assertSame($httpNonEmptySettings, $normalized['inbounds'][2]['settings']);
        $this->assertSame($socksSettings, $normalized['inbounds'][3]['settings']);
    }

    public function test_it_decodes_string_xhttp_extra_as_an_object(): void
    {
        $profile = [
            'outbounds' => [[
                'protocol' => 'vless',
                'streamSettings' => [
                    'network' => 'xhttp',
                    'xhttpSettings' => [
                        'extra' => '{"noGRPCHeader":false,"xmux":{"maxConnections":1}}',
                    ],
                ],
            ]],
        ];

        $normalized = (new XrayJsonProfileNormalizer())->normalizeProfile($profile);

        $extra = $normalized['outbounds'][0]['streamSettings']['xhttpSettings']['extra'];

        $this->assertInstanceOf(stdClass::class, $extra);
        $this->assertFalse($extra->noGRPCHeader);
        $this->assertSame(1, $extra->xmux->maxConnections);
    }

    public function test_it_normalizes_empty_websocket_headers_array_to_an_object(): void
    {
        $profile = [
            'outbounds' => [[
                'protocol' => 'vless',
                'streamSettings' => [
                    'network' => 'ws',
                    'wsSettings' => [
                        'path' => '/',
                        'headers' => [],
                    ],
                ],
            ]],
        ];

        $normalized = (new XrayJsonProfileNormalizer())->normalizeProfile($profile);

        $headers = $normalized['outbounds'][0]['streamSettings']['wsSettings']['headers'];

        $this->assertInstanceOf(stdClass::class, $headers);
        $this->assertSame([], get_object_vars($headers));
    }

    public function test_it_removes_grpc_mode_for_v2raytun_only(): void
    {
        $profile = [
            'outbounds' => [[
                'protocol' => 'vless',
                'streamSettings' => [
                    'network' => 'grpc',
                    'grpcSettings' => [
                        'serviceName' => 'grpc-service',
                        'mode' => true,
                    ],
                ],
            ]],
        ];

        $normalizer = new XrayJsonProfileNormalizer();

        $v2rayTun = $normalizer->normalizeProfile($profile, 'v2raytun');
        $happ = $normalizer->normalizeProfile($profile, 'happ');

        $this->assertArrayNotHasKey('mode', $v2rayTun['outbounds'][0]['streamSettings']['grpcSettings']);
        $this->assertTrue($happ['outbounds'][0]['streamSettings']['grpcSettings']['mode']);
    }

    public function test_it_converts_routing_port_arrays_to_happ_compatible_strings(): void
    {
        $profile = [
            'routing' => [
                'rules' => [[
                    'type' => 'field',
                    'port' => ['25', 123, ' 443 '],
                    'outboundTag' => 'direct',
                ]],
            ],
        ];

        $normalized = (new XrayJsonProfileNormalizer())->normalizeProfile($profile);

        $this->assertSame('25,123,443', $normalized['routing']['rules'][0]['port']);
    }
}
