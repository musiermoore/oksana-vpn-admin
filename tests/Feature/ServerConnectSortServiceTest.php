<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\Server\ConnectGroupSortData;
use App\DTOs\Server\ServerConnectItemSortData;
use App\Models\Proxy;
use App\Models\Server;
use App\Models\VlessExternalSubscription;
use App\Models\XrayInbound;
use App\Services\ServerConnectSortService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerConnectSortServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sorts_servers_and_external_subscriptions_in_shared_connect_list(): void
    {
        $serverOne = $this->createServer('One', 'ONE', 2);
        $serverTwo = $this->createServer('Two', 'TWO', 1);
        $externalSubscription = VlessExternalSubscription::query()->create([
            'name' => 'External',
            'sort_order' => 0,
            'type' => VlessExternalSubscription::TYPE_DIRECT,
            'source_url' => 'vless://uuid@external.example.com:443?type=tcp&security=reality#external',
            'include_in_main_subscription' => true,
            'include_in_whitelist' => false,
            'is_free' => true,
            'is_active' => true,
            'is_ready' => true,
        ]);

        app(ServerConnectSortService::class)->sortGroups(new ConnectGroupSortData(
            items: [
                ['type' => 'server', 'id' => $serverOne->id],
                ['type' => 'external_subscription', 'id' => $externalSubscription->id],
                ['type' => 'server', 'id' => $serverTwo->id],
            ],
        ));

        $this->assertDatabaseHas('servers', [
            'id' => $serverOne->id,
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('vless_external_subscriptions', [
            'id' => $externalSubscription->id,
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('servers', [
            'id' => $serverTwo->id,
            'sort_order' => 2,
        ]);
    }

    public function test_it_sorts_mixed_inbounds_and_proxies_inside_server(): void
    {
        $server = $this->createServer('Latvia', 'LV', 0);

        $firstInbound = XrayInbound::query()->create([
            'server_id' => $server->id,
            'external_id' => 11,
            'sort_order' => 0,
            'is_active' => true,
            'is_public' => true,
            'params' => ['id' => 11, 'protocol' => 'vless'],
        ]);

        $proxy = Proxy::query()->create([
            'name' => 'Proxy First',
            'host' => 'proxy.example.com',
            'port' => 8443,
            'server_id' => $server->id,
            'sort_order' => 1,
            'xray_inbound_id' => $firstInbound->id,
            'is_https' => true,
            'is_ready' => true,
        ]);

        $secondInbound = XrayInbound::query()->create([
            'server_id' => $server->id,
            'external_id' => 22,
            'sort_order' => 2,
            'is_active' => true,
            'is_public' => true,
            'params' => ['id' => 22, 'protocol' => 'vless'],
        ]);

        app(ServerConnectSortService::class)->sortServerItems($server, new ServerConnectItemSortData(
            items: [
                ['type' => 'proxy', 'id' => $proxy->id],
                ['type' => 'inbound', 'id' => $secondInbound->id],
                ['type' => 'inbound', 'id' => $firstInbound->id],
            ],
        ));

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
    }

    private function createServer(string $name, string $code, int $sortOrder): Server
    {
        return Server::query()->create([
            'name' => $name,
            'code' => $code,
            'sort_order' => $sortOrder,
            'ip' => '10.0.0.1',
            'type' => Server::TYPE_VLESS,
            'is_https' => true,
            'is_active' => true,
            'is_ready' => true,
        ]);
    }
}
