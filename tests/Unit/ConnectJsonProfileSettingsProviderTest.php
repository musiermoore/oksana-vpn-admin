<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\XrayRouting;
use App\Models\XrayRoutingDnsSettings;
use App\Services\Subscriptions\ConnectJsonProfileSettingsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectJsonProfileSettingsProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_dns_resource_is_used_for_simple_configs(): void
    {
        XrayRoutingDnsSettings::query()->create([
            'name' => 'Simple config DNS',
            'servers' => [
                ['address' => '8.8.8.8', 'domains' => ['domain:openai.com'], 'skipFallback' => true],
                ['address' => '77.88.8.8', 'domains' => ['geosite:category-ru'], 'skipFallback' => true],
                '8.8.8.8',
            ],
            'query_strategy' => 'UseIPv4',
            'is_active' => true,
            'is_default' => true,
        ]);

        $dns = app(ConnectJsonProfileSettingsProvider::class)->dns();

        $this->assertSame('UseIPv4', $dns['queryStrategy']);
        $this->assertSame('77.88.8.8', $dns['servers'][1]['address']);
    }

    public function test_global_and_server_specific_rules_are_available_to_simple_configs(): void
    {
        XrayRouting::query()->create([
            'name' => 'Global domains',
            'outbound' => 'direct',
            'subscription_types' => ['connect'],
            'rules' => ['domain' => ['geosite:category-ru']],
            'is_global' => true,
            'is_active' => true,
        ]);
        XrayRouting::query()->create([
            'name' => 'Server domains',
            'outbound' => 'direct',
            'subscription_types' => ['connect'],
            'xray_inbound_ids' => [42],
            'rules' => ['domain' => ['domain:example.ru']],
            'is_global' => false,
            'is_active' => true,
        ]);

        $provider = app(ConnectJsonProfileSettingsProvider::class);
        $globalOnly = $provider->routing('connect', 7)['rules'];
        $serverRules = $provider->routing('connect', 42)['rules'];

        $this->assertCount(1, $globalOnly);
        $this->assertCount(2, $serverRules);
        $this->assertSame('geosite:category-ru', $globalOnly[0]['domain'][0]);
        $this->assertSame('domain:example.ru', $serverRules[1]['domain'][0]);
    }
}
