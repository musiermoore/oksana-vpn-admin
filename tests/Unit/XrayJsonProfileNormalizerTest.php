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
}
