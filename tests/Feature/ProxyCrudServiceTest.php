<?php

namespace Tests\Feature;

use App\DTOs\Proxy\ProxyData;
use App\DTOs\Proxy\ProxyHostData;
use App\Models\Proxy;
use App\Models\ProxyGroup;
use App\Models\Server;
use App\Models\XrayInbound;
use App\Services\Crud\ProxyCrudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelData\DataCollection;
use Tests\TestCase;

class ProxyCrudServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_update_replace_proxy_server(): void
    {
        $serverOne = $this->createServer('LV1');
        $serverTwo = $this->createServer('FI1');
        $proxyGroup = ProxyGroup::query()->create(['name' => 'Nordic']);
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
            hosts: new DataCollection(ProxyHostData::class, [
                new ProxyHostData(
                    host: 'proxy.example.com',
                    port: 443,
                    sort: 0,
                    isActive: true,
                    isHttps: true,
                ),
                new ProxyHostData(
                    host: 'cascade.example.com',
                    port: 8443,
                    sort: 1,
                    isActive: true,
                    isHttps: true,
                    proxyServerId: $serverTwo->id,
                ),
            ]),
            isHttps: true,
            isReady: true,
            hideMainNodeName: true,
            serverId: $serverOne->id,
            inboundId: 10,
            description: 'Primary proxy',
            proxyGroupId: $proxyGroup->id,
        ));

        $this->assertDatabaseHas('proxies', [
            'id' => $proxy->id,
            'name' => 'Ru Proxy',
            'server_id' => $serverOne->id,
            'proxy_group_id' => $proxyGroup->id,
            'xray_inbound_id' => $inbound->id,
            'is_ready' => true,
            'hide_main_node_name' => true,
        ]);
        $hostId = (int) $proxy->hosts->first()->id;
        $this->assertDatabaseHas('proxy_hosts', [
            'id' => $hostId,
            'proxy_id' => $proxy->id,
            'host' => 'proxy.example.com',
            'port' => 443,
            'sort' => 0,
            'is_https' => true,
        ]);
        $this->assertDatabaseHas('proxy_hosts', [
            'proxy_id' => $proxy->id,
            'host' => 'cascade.example.com',
            'proxy_server_id' => $serverTwo->id,
            'is_https' => true,
        ]);
        $this->assertSame($serverOne->id, $proxy->server?->id);

        $proxy = $service->update($proxy, new ProxyData(
            name: 'Ru Proxy',
            hosts: new DataCollection(ProxyHostData::class, [new ProxyHostData(
                host: 'proxy-updated.example.com',
                port: 8443,
                sort: 2,
                isActive: true,
                id: $hostId,
            )]),
            isHttps: false,
            isReady: false,
            hideMainNodeName: false,
            serverId: $serverTwo->id,
            inboundId: null,
            description: null,
            proxyGroupId: $proxyGroup->id,
        ));

        $this->assertSame($serverTwo->id, $proxy->server?->id);
        $this->assertDatabaseHas('proxy_hosts', [
            'id' => $hostId,
            'proxy_id' => $proxy->id,
            'host' => 'proxy-updated.example.com',
            'port' => 8443,
            'sort' => 2,
            'is_https' => false,
        ]);
        $this->assertDatabaseHas('proxies', [
            'id' => $proxy->id,
            'server_id' => $serverTwo->id,
            'xray_inbound_id' => null,
            'is_https' => false,
            'is_ready' => false,
            'hide_main_node_name' => false,
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
            'server_id' => $server->id,
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
