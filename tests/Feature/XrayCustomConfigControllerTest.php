<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use App\Models\XrayCustomConfig;
use App\Models\XrayInbound;
use App\Models\XrayRoutingDnsSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class XrayCustomConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_includes_inbound_protocol_and_remark(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $server = Server::query()->create([
            'name' => 'Germany',
            'code' => 'DE-1',
            'sort_order' => 1,
            'ip' => '10.0.0.10',
            'type' => Server::TYPE_VLESS,
        ]);
        $inbound = XrayInbound::query()->create([
            'server_id' => $server->id,
            'external_id' => 1,
            'sort_order' => 1,
            'is_active' => true,
            'is_public' => true,
            'params' => ['protocol' => 'vless', 'remark' => 'Main VLESS'],
        ]);

        $this->actingAs($admin)
            ->get(route('xray-custom-configs.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('XrayCustomConfigs/Form')
                ->where('targets.servers.0.xray_inbounds.0.id', $inbound->id)
                ->where('targets.servers.0.xray_inbounds.0.protocol', 'vless')
                ->where('targets.servers.0.xray_inbounds.0.remark', 'Main VLESS')
            );
    }

    public function test_edit_form_can_disable_a_custom_config(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $config = XrayCustomConfig::query()->create([
            'name' => 'Custom config',
            'slug' => 'custom-config',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->put(route('xray-custom-configs.update', $config), [
                'name' => $config->name,
                'slug' => $config->slug,
                'description' => null,
                'xray_inbound_ids' => [],
                'external_subscription_config_ids' => [],
                'external_subscription_ids' => [],
                'proxy_ids' => [],
                'xray_routing_ids' => [],
                'base_settings_json' => '{}',
                'outbound_groups_json' => '[]',
                'routes_json' => '[]',
                'is_active' => false,
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $this->assertFalse($config->fresh()->is_active);
    }

    public function test_admin_can_create_a_custom_dns_resource_with_structured_servers(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $servers = [
            [
                'address' => '8.8.8.8',
                'domains' => ['domain:openai.com'],
                'skipFallback' => true,
            ],
            '8.8.8.8',
        ];

        $this->actingAs($admin)
            ->postJson(route('xray-dns-settings.store'), [
                'name' => 'Structured DNS',
                'description' => 'DNS for a custom JSON config.',
                'servers' => $servers,
                'query_strategy' => 'UseIPv4',
                'is_default' => false,
            ])
            ->assertOk();

        $this->assertSame($servers, XrayRoutingDnsSettings::query()->firstOrFail()->servers);
    }
}
