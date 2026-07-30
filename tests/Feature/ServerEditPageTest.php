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
                ->where('server.connect_items.0.type', 'proxy')
                ->where('server.connect_items.0.subtitle', 'proxy.example.com:443 · Inbound #101')
                ->where('server.connect_items.1.type', 'inbound')
                ->where('server.connect_items.1.entity_id', $inbound->id)
            );
    }
}
