<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\Subscription\NormalizedNode;
use App\Models\XrayCustomConfig;
use App\Models\XrayCustomConfigOutboundGroup;
use App\Models\XrayCustomConfigRoute;
use App\Models\XrayRouting;
use App\Models\XrayRoutingDnsSettings;
use App\Models\XrayRoutingGeodata;
use App\Services\Subscriptions\Builders\ConnectJsonBuilder;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectJsonBuilderCustomConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_grouped_custom_config_builds_balancers_routes_fallback_and_direct_default(): void
    {
        $fallback = new XrayCustomConfigOutboundGroup([
            'name' => 'Finland',
            'tag' => 'finland',
            'strategy' => 'leastPing',
            'strategy_settings' => [],
            'is_active' => true,
        ]);
        $primary = new XrayCustomConfigOutboundGroup([
            'name' => 'Germany',
            'tag' => 'germany',
            'strategy' => 'roundRobin',
            'fallback_group_id' => 2,
            'xray_inbound_ids' => [10],
            'external_subscription_ids' => [20],
            'strategy_settings' => [],
            'is_active' => true,
        ]);
        $primary->setRelation('fallbackGroup', $fallback);

        $config = new XrayCustomConfig([
            'name' => 'Auto',
            'base_settings' => [],
            'xray_routing_ids' => [1],
        ]);
        XrayRouting::query()->create([
            'name' => 'Whitelist',
            'outbound' => 'direct',
            'subscription_types' => ['connect'],
            'xray_inbound_ids' => [10],
            'rules' => ['domain' => ['domain:example.ru']],
            'is_active' => true,
        ]);
        $config->setRelation('dnsSettings', new XrayRoutingDnsSettings([
            'servers' => ['8.8.8.8', '1.1.1.1'],
            'query_strategy' => 'UseIPv4',
            'enable_parallel_query' => true,
        ]));
        $config->setRelation('geodata', new XrayRoutingGeodata([
            'assets' => [
                ['file' => 'geosite.dat', 'url' => 'https://example.test/geosite.dat'],
            ],
        ]));
        $config->setRelation('outboundGroups', new Collection([$primary, $fallback]));
        $config->setRelation('routes', new Collection([
            new XrayCustomConfigRoute([
                'rules' => ['domain' => ['domain:chatgpt.com']],
                'target_type' => 'balancer',
                'target_tag' => 'germany',
                'is_active' => true,
            ]),
            new XrayCustomConfigRoute([
                'rules' => ['network' => ['tcp', 'udp']],
                'target_type' => 'balancer',
                'target_tag' => 'germany',
                'is_active' => true,
            ]),
        ]));

        $node = new NormalizedNode(
            id: 'node-1',
            serverName: 'Germany 1',
            sortGroupOrder: 0,
            sortItemOrder: 0,
            protocol: 'vless',
            transport: 'tcp',
            uri: 'vless://11111111-1111-1111-1111-111111111111@germany.example.com:443?type=tcp&security=tls&sni=germany.example.com#Germany',
            serverId: 1,
            configId: 1,
            sourceType: 'server',
            sortServerName: 'Germany',
            meta: ['xray_inbound_id' => 10],
        );
        $externalNode = new NormalizedNode(
            id: 'external-1',
            serverName: 'External subscription',
            sortGroupOrder: 0,
            sortItemOrder: 1,
            protocol: 'vless',
            transport: 'tcp',
            uri: 'vless://22222222-2222-2222-2222-222222222222@external.example.com:443?type=tcp&security=tls&sni=external.example.com#External',
            serverId: 2,
            configId: 2,
            sourceType: 'external_subscription',
            sortServerName: 'external subscription',
            meta: ['external_subscription_id' => 20, 'external_subscription_config_id' => 200],
        );

        $payload = json_decode(app(ConnectJsonBuilder::class)->buildForCustomConfig([$node, $externalNode], $config)->content, true, 512, JSON_THROW_ON_ERROR);
        $profile = $payload[0];

        $this->assertContains('germany-2', array_column($profile['outbounds'], 'tag'));
        $this->assertSame(['8.8.8.8', '1.1.1.1'], data_get($profile, 'dns.servers'));
        $this->assertSame('direct', data_get($profile, 'geodata.outbound'));
        $this->assertSame(['germany-', 'finland-'], data_get($profile, 'observatory.subjectSelector'));
        $this->assertSame('roundRobin', data_get($profile, 'routing.balancers.0.strategy.type'));
        $this->assertStringContainsString('"settings": {}', (string) app(ConnectJsonBuilder::class)->buildForCustomConfig([$node], $config)->content);
        $this->assertSame('leastPing', data_get($profile, 'routing.balancers.1.strategy.type'));
        $this->assertSame('germany-fallback-loop', data_get($profile, 'routing.balancers.0.fallbackTag'));
        $this->assertSame('domain:example.ru', data_get($profile, 'routing.rules.1.domain.0'));
        $this->assertSame('direct', data_get($profile, 'routing.rules.1.outboundTag'));
        $this->assertSame('germany', data_get($profile, 'routing.rules.2.balancerTag'));
        $this->assertSame('tcp,udp', data_get($profile, 'routing.rules.3.network'));
        $this->assertSame('germany', data_get($profile, 'routing.rules.3.balancerTag'));
        $this->assertCount(4, $profile['routing']['rules']);
        $this->assertContains('loopback', array_column($profile['outbounds'], 'protocol'));
    }
}
