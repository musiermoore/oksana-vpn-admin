<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\XrayJsonSetting;
use App\Models\XrayRouting;
use App\Models\XrayRoutingDnsSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XrayGlobalConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_global_json_configuration(): void
    {
        $admin = User::query()->create([
            'name' => 'Global JSON Admin',
            'telegram' => '@global-json-admin',
            'telegram_id' => 'global-json-admin',
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('xray-global-config.update'), [
                'dns_json' => json_encode([
                    'queryStrategy' => 'UseIPv4',
                    'servers' => ['8.8.8.8'],
                ], JSON_THROW_ON_ERROR),
                'routing_json' => json_encode([
                    'domainStrategy' => 'IPIfNonMatch',
                ], JSON_THROW_ON_ERROR),
                'rules_json' => json_encode([
                    [
                        'domain' => ['domain:example.com'],
                        'outboundTag' => 'direct',
                    ],
                ], JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('xray_json_settings', [
            'source' => 'manual_global',
            'is_active' => true,
        ]);
        $this->assertSame(
            'IPIfNonMatch',
            data_get(XrayJsonSetting::query()->where('source', 'manual_global')->firstOrFail()->routing, 'domainStrategy'),
        );
        $this->assertSame(['8.8.8.8'], XrayRoutingDnsSettings::query()->where('is_default', true)->firstOrFail()->servers);
        $this->assertDatabaseHas('xray_routings', [
            'source' => 'manual_global',
            'is_global' => true,
            'outbound' => 'direct',
        ]);
    }

    public function test_admin_can_save_structured_dns_servers_for_global_json_configuration(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $servers = [
            [
                'address' => '8.8.8.8',
                'domains' => ['domain:openai.com', 'domain:chatgpt.com'],
                'skipFallback' => true,
            ],
            [
                'address' => '77.88.8.8',
                'domains' => ['geosite:category-ru'],
                'skipFallback' => true,
            ],
            '8.8.8.8',
        ];

        $this->actingAs($admin)
            ->put(route('xray-global-config.update'), [
                'dns_json' => json_encode([
                    'queryStrategy' => 'UseIPv4',
                    'servers' => $servers,
                ], JSON_THROW_ON_ERROR),
                'routing_json' => json_encode(['domainStrategy' => 'AsIs'], JSON_THROW_ON_ERROR),
                'rules_json' => json_encode([], JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect();

        $this->assertSame(
            $servers,
            XrayRoutingDnsSettings::query()->where('is_default', true)->firstOrFail()->servers,
        );
    }
}
