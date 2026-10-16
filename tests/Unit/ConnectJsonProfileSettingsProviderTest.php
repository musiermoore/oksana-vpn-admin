<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\XrayRouting;
use App\Models\XrayRoutingDnsSettings;
use App\Models\XrayJsonSetting;
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

    public function test_unmatched_scoped_rule_uses_default_routing_for_the_target(): void
    {
        $defaultRules = [['type' => 'field', 'network' => 'tcp,udp', 'outboundTag' => 'proxy']];
        config(['connect_json.routing.rules' => $defaultRules]);

        XrayRouting::query()->create([
            'name' => 'Other inbound only',
            'outbound' => 'direct',
            'subscription_types' => [XrayRouting::SUBSCRIPTION_CONNECT],
            'xray_inbound_ids' => [101],
            'rules' => ['domain' => ['domain:only-one.example']],
            'is_active' => true,
        ]);

        $rules = app(ConnectJsonProfileSettingsProvider::class)
            ->routing(XrayRouting::SUBSCRIPTION_CONNECT, 202)['rules'];

        $this->assertSame($defaultRules, $rules);
    }

    public function test_connect_v2_and_external_scopes_select_only_their_own_rules(): void
    {
        config(['connect_json.routing.rules' => [[
            'type' => 'field',
            'domain' => ['domain:default.example'],
            'outboundTag' => 'proxy',
        ]]]);

        XrayRouting::query()->create([
            'name' => 'Connect v2 rule',
            'outbound' => 'direct',
            'subscription_types' => [XrayRouting::SUBSCRIPTION_CONNECT_V2],
            'xray_inbound_ids' => [42],
            'rules' => ['domain' => ['domain:v2.example']],
            'is_active' => true,
        ]);
        XrayRouting::query()->create([
            'name' => 'External rule',
            'outbound' => 'proxy',
            'subscription_types' => [XrayRouting::SUBSCRIPTION_EXTERNAL],
            'external_subscription_config_ids' => [88],
            'rules' => ['domain' => ['domain:external.example']],
            'is_active' => true,
        ]);

        $provider = app(ConnectJsonProfileSettingsProvider::class);

        $this->assertSame('domain:v2.example', $provider
            ->routing(XrayRouting::SUBSCRIPTION_CONNECT_V2, 42)['rules'][0]['domain'][0]);
        $this->assertSame('domain:external.example', $provider
            ->routing(XrayRouting::SUBSCRIPTION_EXTERNAL, null, 88)['rules'][0]['domain'][0]);
        $this->assertSame('domain:default.example', $provider
            ->routing(XrayRouting::SUBSCRIPTION_EXTERNAL, 42)['rules'][0]['domain'][0]);
    }

    public function test_manual_global_json_settings_override_default_profile_settings(): void
    {
        XrayJsonSetting::query()->create([
            'name' => 'Global JSON',
            'source' => 'manual_global',
            'dns' => [
                'queryStrategy' => 'UseIPv4',
                'servers' => ['9.9.9.9'],
            ],
            'routing' => ['domainStrategy' => 'IPIfNonMatch'],
            'is_active' => true,
        ]);
        XrayRouting::query()->create([
            'name' => 'Global JSON rule',
            'source' => 'manual_global',
            'source_key' => '0',
            'outbound' => 'direct',
            'subscription_types' => ['connect', 'connect_wl'],
            'is_global' => true,
            'rules' => ['domain' => ['domain:example.com']],
            'is_active' => true,
        ]);

        $provider = app(ConnectJsonProfileSettingsProvider::class);
        $routing = $provider->routing('connect');

        $this->assertSame(['9.9.9.9'], $provider->dns()['servers']);
        $this->assertSame('IPIfNonMatch', $routing['domainStrategy']);
        $this->assertSame('domain:example.com', $routing['rules'][0]['domain'][0]);
    }
}
