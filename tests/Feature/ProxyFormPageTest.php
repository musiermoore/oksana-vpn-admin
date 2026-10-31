<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Proxy;
use App\Models\Server;
use App\Models\User;
use App\Models\XrayInbound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProxyFormPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_returns_inbound_options_grouped_by_server(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
        ]);

        $server = Server::query()->create([
            'name' => 'Finland',
            'code' => 'FI-1',
            'sort_order' => 1,
            'ip' => '10.0.0.10',
            'type' => Server::TYPE_VLESS,
        ]);

        XrayInbound::query()->create([
            'server_id' => $server->id,
            'external_id' => 101,
            'sort_order' => 1,
            'is_active' => true,
            'is_public' => true,
            'params' => [
                'protocol' => 'vless',
                'port' => 443,
                'remark' => 'Reality TCP',
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('proxies.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Proxies/Form')
                ->where('inbound_options_by_server.'.$server->id.'.0.value', 101)
                ->where('inbound_options_by_server.'.$server->id.'.0.label', 'Reality TCP · vless · 443 (узел №101)')
            );
    }

    public function test_edit_page_returns_selected_proxy_and_inbound_options(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
        ]);

        $server = Server::query()->create([
            'name' => 'Sweden',
            'code' => 'SE-1',
            'sort_order' => 1,
            'ip' => '10.0.0.11',
            'type' => Server::TYPE_VLESS,
        ]);

        $inbound = XrayInbound::query()->create([
            'server_id' => $server->id,
            'external_id' => 202,
            'sort_order' => 1,
            'is_active' => true,
            'is_public' => true,
            'params' => [
                'protocol' => 'vless',
                'port' => 443,
                'remark' => 'Main inbound',
            ],
        ]);

        $proxy = Proxy::query()->create([
            'name' => 'Proxy One',
            'server_id' => $server->id,
            'sort_order' => 0,
            'xray_inbound_id' => $inbound->id,
            'is_https' => true,
            'is_ready' => true,
            'hide_main_node_name' => false,
        ]);
        $proxy->hosts()->create([
            'host' => 'proxy.example.com',
            'port' => 443,
            'sort' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('proxies.edit', $proxy))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Proxies/Form')
                ->where('proxy.inbound_id', 202)
                ->where('inbound_options_by_server.'.$server->id.'.0.value', 202)
                ->where('inbound_options_by_server.'.$server->id.'.0.label', 'Main inbound · vless · 443 (узел №202)')
                ->where('proxy.hosts.0.host', 'proxy.example.com')
            );
    }

    public function test_each_active_host_including_entrypoint_requires_a_haproxy_server(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $server = Server::query()->create([
            'name' => 'Germany',
            'code' => 'DE-1',
            'sort_order' => 1,
            'ip' => '10.0.0.12',
            'type' => Server::TYPE_VLESS,
        ]);
        $payload = [
            'name' => 'Cascade proxy',
            'hosts' => [
                ['host' => 'entry.example.com', 'port' => 443, 'sort' => 0, 'is_active' => true],
                ['host' => 'hop.example.com', 'port' => 8443, 'sort' => 1, 'is_active' => true],
            ],
            'server_id' => $server->id,
            'is_https' => true,
            'is_ready' => false,
            'hide_main_node_name' => false,
        ];

        $this->actingAs($admin)
            ->post(route('proxies.store'), $payload)
            ->assertSessionHasErrors(['hosts.0.proxy_server_id', 'hosts.1.proxy_server_id']);

        $payload['hosts'][0]['proxy_server_id'] = $server->id;
        $payload['hosts'][1]['proxy_server_id'] = $server->id;

        $this->actingAs($admin)
            ->post(route('proxies.store'), $payload)
            ->assertRedirect();

        $proxy = Proxy::query()->where('name', 'Cascade proxy')->firstOrFail();
        $this->assertDatabaseHas('proxy_hosts', [
            'proxy_id' => $proxy->id,
            'host' => 'entry.example.com',
            'proxy_server_id' => $server->id,
        ]);
    }

    public function test_proxies_page_shows_system_name_for_admins(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $server = Server::query()->create([
            'name' => 'Germany',
            'code' => 'DE-1',
            'sort_order' => 1,
            'ip' => '10.0.0.12',
            'type' => Server::TYPE_VLESS,
        ]);
        $proxy = Proxy::query()->create([
            'name' => 'Admin label',
            'system_name' => 'Admin label',
            'server_id' => $server->id,
            'sort_order' => 0,
            'is_https' => true,
            'is_ready' => true,
        ]);
        $proxy->hosts()->create([
            'host' => 'proxy.example.com',
            'port' => 443,
            'sort' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('proxies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Proxies/Index')
                ->where('proxies.0.system_name', 'Admin label')
            );
    }
}
