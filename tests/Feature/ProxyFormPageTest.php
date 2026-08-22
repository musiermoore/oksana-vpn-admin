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
                'remark' => 'Reality TCP',
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('proxies.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Proxies/Form')
                ->where('inbound_options_by_server.'.$server->id.'.0.value', 101)
                ->where('inbound_options_by_server.'.$server->id.'.0.label', 'Inbound #101 - Reality TCP')
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
                'remark' => 'Main inbound',
            ],
        ]);

        $proxy = Proxy::query()->create([
            'name' => 'Proxy One',
            'host' => 'proxy.example.com',
            'port' => 443,
            'server_id' => $server->id,
            'sort_order' => 0,
            'xray_inbound_id' => $inbound->id,
            'is_https' => true,
            'is_ready' => true,
            'hide_main_node_name' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('proxies.edit', $proxy))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Proxies/Form')
                ->where('proxy.inbound_id', 202)
                ->where('inbound_options_by_server.'.$server->id.'.0.value', 202)
                ->where('inbound_options_by_server.'.$server->id.'.0.label', 'Inbound #202 - Main inbound')
            );
    }
}
