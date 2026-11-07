<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use App\Models\XrayCustomConfig;
use App\Models\XrayCustomConfigOutboundGroup;
use App\Models\XrayCustomConfigRoute;
use App\Models\XrayCustomConfigUser;
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

    public function test_edit_discards_proxy_ids_that_no_longer_exist(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $config = XrayCustomConfig::query()->create([
            'name' => 'Custom config',
            'slug' => 'custom-config',
            'proxy_ids' => [999999],
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
                'proxy_ids' => [999999],
                'xray_routing_ids' => [],
                'base_settings_json' => '{}',
                'outbound_groups_json' => '[]',
                'routes_json' => '[]',
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $this->assertSame([], $config->fresh()->proxy_ids);
    }

    public function test_edit_removes_group_members_that_are_not_selected_as_config_sources(): void
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
            'params' => ['protocol' => 'vless'],
        ]);
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
                'xray_inbound_ids' => [$inbound->id],
                'external_subscription_config_ids' => [],
                'external_subscription_ids' => [],
                'proxy_ids' => [],
                'xray_routing_ids' => [],
                'base_settings_json' => '{}',
                'outbound_groups_json' => json_encode([[
                    'name' => 'Germany pool',
                    'tag' => 'germany-pool',
                    'strategy' => 'roundRobin',
                    'xray_inbound_ids' => [$inbound->id, 999999],
                    'external_subscription_config_ids' => [],
                    'external_subscription_ids' => [],
                    'proxy_ids' => [],
                    'is_active' => true,
                ]], JSON_THROW_ON_ERROR),
                'routes_json' => '[]',
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $group = XrayCustomConfigOutboundGroup::query()
            ->where('xray_custom_config_id', $config->id)
            ->firstOrFail();

        $this->assertSame([$inbound->id], $group->xray_inbound_ids);
    }

    public function test_admin_can_duplicate_a_custom_config_without_copying_user_assignments(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $assignedUser = User::factory()->create();
        $config = XrayCustomConfig::query()->create([
            'name' => 'Custom config',
            'slug' => 'custom-config',
            'description' => 'Profile description',
            'xray_inbound_ids' => [10, 11],
            'base_settings' => ['log' => ['loglevel' => 'warning']],
            'is_active' => true,
            'is_public' => false,
            'sort_order' => 3,
        ]);
        $fallback = XrayCustomConfigOutboundGroup::query()->create([
            'xray_custom_config_id' => $config->id,
            'name' => 'Fallback',
            'tag' => 'fallback',
            'strategy' => 'random',
            'sort_order' => 0,
        ]);
        XrayCustomConfigOutboundGroup::query()->create([
            'xray_custom_config_id' => $config->id,
            'name' => 'Primary',
            'tag' => 'primary',
            'strategy' => 'roundRobin',
            'fallback_group_id' => $fallback->id,
            'sort_order' => 1,
        ]);
        XrayCustomConfigRoute::query()->create([
            'xray_custom_config_id' => $config->id,
            'name' => 'Route',
            'rules' => [['domain' => ['example.com']]],
            'target_type' => 'balancer',
            'target_tag' => 'primary',
            'sort_order' => 2,
        ]);
        XrayCustomConfigUser::query()->create([
            'xray_custom_config_id' => $config->id,
            'user_id' => $assignedUser->id,
        ]);
        XrayCustomConfig::query()->create([
            'name' => 'Existing copy',
            'slug' => 'custom-config-copy',
            'is_active' => false,
            'sort_order' => 4,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('xray-custom-configs.duplicate', $config));

        $copy = XrayCustomConfig::query()->where('slug', 'custom-config-copy-2')->firstOrFail();
        $response->assertRedirect(route('xray-custom-configs.edit', $copy));
        $this->assertSame('Custom config (копия)', $copy->name);
        $this->assertSame('Profile description', $copy->description);
        $this->assertSame([10, 11], $copy->xray_inbound_ids);
        $this->assertSame(['log' => ['loglevel' => 'warning']], $copy->base_settings);
        $this->assertFalse($copy->is_active);
        $this->assertFalse($copy->is_public);
        $this->assertSame(5, $copy->sort_order);
        $this->assertDatabaseHas('xray_custom_config_outbound_groups', [
            'xray_custom_config_id' => $copy->id,
            'tag' => 'primary',
        ]);
        $copiedPrimary = $copy->outboundGroups()->where('tag', 'primary')->firstOrFail();
        $this->assertSame(
            $copy->outboundGroups()->where('tag', 'fallback')->value('id'),
            $copiedPrimary->fallback_group_id,
        );
        $this->assertSame(1, XrayCustomConfigRoute::query()->where('xray_custom_config_id', $copy->id)->count());
        $this->assertSame(0, XrayCustomConfigUser::query()->where('xray_custom_config_id', $copy->id)->count());
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

    public function test_admin_can_update_an_existing_custom_dns_resource(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $settings = XrayRoutingDnsSettings::query()->create([
            'name' => 'Old DNS',
            'servers' => ['1.1.1.1'],
            'query_strategy' => 'UseIPv4',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->putJson(route('xray-dns-settings.update', $settings), [
                'name' => 'Updated DNS',
                'description' => 'Updated DNS resource.',
                'servers' => [
                    ['address' => '8.8.8.8', 'skipFallback' => false],
                ],
                'query_strategy' => 'UseIPv4',
                'is_default' => true,
            ])
            ->assertOk();

        $settings->refresh();
        $this->assertSame('Updated DNS', $settings->name);
        $this->assertSame([['address' => '8.8.8.8', 'skipFallback' => false]], $settings->servers);
        $this->assertTrue($settings->is_default);
    }

    public function test_custom_config_form_includes_the_dns_update_url(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $settings = XrayRoutingDnsSettings::query()->create([
            'name' => 'Editable DNS',
            'servers' => ['8.8.8.8'],
            'query_strategy' => 'UseIPv4',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('xray-custom-configs.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dns_settings.0.id', $settings->id)
                ->where('dns_settings.0.update_url', route('xray-dns-settings.update', $settings))
            );
    }
}
