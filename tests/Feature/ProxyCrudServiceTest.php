<?php

namespace Tests\Feature;

use App\DTOs\Proxy\ProxyData;
use App\Models\Proxy;
use App\Models\Server;
use App\Models\XrayInbound;
use App\Services\Crud\ProxyCrudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProxyCrudServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_update_replace_proxy_server(): void
    {
        $serverOne = $this->createServer('LV1');
        $serverTwo = $this->createServer('FI1');
        $inbound = XrayInbound::query()->create([
            'server_id' => $serverOne->id,
            'external_id' => 10,
            'is_active' => true,
            'is_public' => true,
            'params' => ['id' => 10, 'protocol' => 'vless'],
        ]);

        $service = app(ProxyCrudService::class);

        $proxy = $service->create(new ProxyData(
            name: 'Ru Proxy',
            host: 'proxy.example.com',
            port: 443,
            serverId: $serverOne->id,
            inboundId: 10,
            isHttps: true,
            isReady: true,
            description: 'Primary proxy',
        ));

        $this->assertDatabaseHas('proxies', [
            'id' => $proxy->id,
            'name' => 'Ru Proxy',
            'host' => 'proxy.example.com',
            'port' => 443,
            'server_id' => $serverOne->id,
            'xray_inbound_id' => $inbound->id,
            'is_ready' => true,
        ]);
        $this->assertSame($serverOne->id, $proxy->server?->id);

        $proxy = $service->update($proxy, new ProxyData(
            name: 'Ru Proxy',
            host: 'proxy-updated.example.com',
            port: 8443,
            serverId: $serverTwo->id,
            inboundId: null,
            isHttps: false,
            isReady: false,
            description: null,
        ));

        $this->assertSame($serverTwo->id, $proxy->server?->id);
        $this->assertDatabaseHas('proxies', [
            'id' => $proxy->id,
            'host' => 'proxy-updated.example.com',
            'port' => 8443,
            'server_id' => $serverTwo->id,
            'xray_inbound_id' => null,
            'is_https' => false,
            'is_ready' => false,
        ]);
    }

    public function test_delete_removes_proxy(): void
    {
        $server = $this->createServer('LV1');
        $inbound = XrayInbound::query()->create([
            'server_id' => $server->id,
            'external_id' => 10,
            'is_active' => true,
            'is_public' => true,
            'params' => ['id' => 10, 'protocol' => 'vless'],
        ]);
        $proxy = Proxy::query()->create([
            'name' => 'Ru Proxy',
            'host' => 'proxy.example.com',
            'port' => 443,
            'server_id' => $server->id,
            'xray_inbound_id' => $inbound->id,
            'is_https' => true,
            'is_ready' => true,
        ]);

        app(ProxyCrudService::class)->delete($proxy);

        $this->assertDatabaseMissing('proxies', ['id' => $proxy->id]);
    }

    private function createServer(string $code): Server
    {
        return Server::query()->create([
            'name' => 'Server '.$code,
            'code' => $code,
            'ip' => '10.0.0.1',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
            'is_https' => true,
            'link_host' => strtolower($code).'.example.com',
        ]);
    }
}
