<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\Subscription\NormalizedNode;
use App\Models\XrayCustomConfig;
use App\Models\XrayCustomConfigOutboundGroup;
use App\Models\XrayCustomConfigRoute;
use App\Models\XrayRoutingDnsSettings;
use App\Services\Subscriptions\Builders\ConnectJsonBuilder;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ConnectJsonBuilderCustomConfigTest extends TestCase
{
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
            'strategy_settings' => [],
            'is_active' => true,
        ]);
        $primary->setRelation('fallbackGroup', $fallback);

        $config = new XrayCustomConfig([
            'name' => 'Auto',
            'base_settings' => [],
        ]);
        $config->setRelation('dnsSettings', new XrayRoutingDnsSettings([
            'servers' => ['8.8.8.8', '1.1.1.1'],
            'query_strategy' => 'UseIPv4',
            'enable_parallel_query' => true,
        ]));
        $config->setRelation('geodata', null);
        $config->setRelation('outboundGroups', new Collection([$primary, $fallback]));
        $config->setRelation('routes', new Collection([
            new XrayCustomConfigRoute([
                'rules' => ['domain' => ['domain:chatgpt.com']],
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

        $payload = json_decode(app(ConnectJsonBuilder::class)->buildForCustomConfig([$node], $config)->content, true, 512, JSON_THROW_ON_ERROR);
        $profile = $payload[0];

        $this->assertSame(['8.8.8.8', '1.1.1.1'], data_get($profile, 'dns.servers'));
        $this->assertSame('roundRobin', data_get($profile, 'routing.balancers.0.strategy.type'));
        $this->assertStringContainsString('"settings": {}', (string) app(ConnectJsonBuilder::class)->buildForCustomConfig([$node], $config)->content);
        $this->assertSame('leastPing', data_get($profile, 'routing.balancers.1.strategy.type'));
        $this->assertSame('germany-fallback-loop', data_get($profile, 'routing.balancers.0.fallbackTag'));
        $this->assertSame('germany', data_get($profile, 'routing.rules.1.balancerTag'));
        $this->assertSame('direct', data_get($profile, 'routing.rules.2.outboundTag'));
        $this->assertSame('loopback', data_get($profile, 'outbounds.1.protocol'));
    }
}
