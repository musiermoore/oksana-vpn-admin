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

class ServerEditPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_returns_connect_items_as_plain_array(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
        ]);

        $server = Server::query()->create([
            'name' => 'Sweden',
            'code' => 'SE-1',
            'sort_order' => 1,
            'ip' => '10.0.0.17',
            'type' => Server::TYPE_VLESS,
        ]);

        $inbound = XrayInbound::query()->create([
            'server_id' => $server->id,
            'external_id' => 101,
            'sort_order' => 1,
            'is_active' => true,
            'is_public' => true,
            'params' => [
                'protocol' => 'vless',
                'remark' => 'Main inbound',
            ],
        ]);

        Proxy::query()->create([
            'name' => 'Proxy One',
            'host' => 'proxy.example.com',
            'port' => 443,
            'server_id' => $server->id,
            'sort_order' => 2,
            'xray_inbound_id' => $inbound->id,
            'is_https' => true,
            'is_ready' => true,
        ]);

        $this->actingAs($admin)
            ->get("/servers/{$server->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Servers/Form')
                ->has('server.connect_items', 2)
                ->where('server.connect_items.0.type', 'inbound')
                ->where('server.connect_items.0.entity_id', $inbound->id)
                ->where('server.connect_items.1.type', 'proxy')
                ->where('server.connect_items.1.subtitle', 'proxy.example.com:443 · Inbound #101')
            );
    }

    public function test_edit_page_returns_connect_items_in_persisted_mixed_sort_order_after_reordering(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
        ]);

        $server = Server::query()->create([
            'name' => 'Latvia',
            'code' => 'LV-1',
            'sort_order' => 1,
            'ip' => '10.0.0.18',
            'type' => Server::TYPE_VLESS,
        ]);

        $firstInbound = XrayInbound::query()->create([
            'server_id' => $server->id,
            'external_id' => 101,
            'sort_order' => 0,
            'is_active' => true,
            'is_public' => true,
            'params' => [
                'protocol' => 'vless',
                'remark' => 'First inbound',
            ],
        ]);

        $proxy = Proxy::query()->create([
            'name' => 'Proxy One',
            'host' => 'proxy.example.com',
            'port' => 443,
            'server_id' => $server->id,
            'sort_order' => 1,
            'xray_inbound_id' => $firstInbound->id,
            'is_https' => true,
            'is_ready' => true,
        ]);

        $secondInbound = XrayInbound::query()->create([
            'server_id' => $server->id,
            'external_id' => 202,
            'sort_order' => 2,
            'is_active' => true,
            'is_public' => true,
            'params' => [
                'protocol' => 'vless',
                'remark' => 'Second inbound',
            ],
        ]);

        $this->actingAs($admin)
            ->post(route('servers.sort-connect-items', $server), [
                'items' => [
                    ['type' => 'proxy', 'id' => $proxy->id],
                    ['type' => 'inbound', 'id' => $secondInbound->id],
                    ['type' => 'inbound', 'id' => $firstInbound->id],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('proxies', [
            'id' => $proxy->id,
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('xray_inbounds', [
            'id' => $secondInbound->id,
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('xray_inbounds', [
            'id' => $firstInbound->id,
            'sort_order' => 2,
        ]);

        $this->actingAs($admin)
            ->get("/servers/{$server->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Servers/Form')
                ->has('server.connect_items', 3)
                ->where('server.connect_items.0.type', 'proxy')
                ->where('server.connect_items.0.entity_id', $proxy->id)
                ->where('server.connect_items.1.type', 'inbound')
                ->where('server.connect_items.1.entity_id', $secondInbound->id)
                ->where('server.connect_items.2.type', 'inbound')
                ->where('server.connect_items.2.entity_id', $firstInbound->id)
            );
    }
}
