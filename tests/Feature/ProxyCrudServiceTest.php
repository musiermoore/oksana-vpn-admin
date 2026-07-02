<?php

namespace Tests\Feature;

use App\DTOs\Proxy\ProxyData;
use App\Models\Proxy;
use App\Models\Server;
use App\Services\Crud\ProxyCrudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProxyCrudServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_update_sync_server_links(): void
    {
        $serverOne = $this->createServer('LV1');
        $serverTwo = $this->createServer('FI1');

        $service = app(ProxyCrudService::class);

        $proxy = $service->create(new ProxyData(
            name: 'Ru Proxy',
            host: 'proxy.example.com',
            port: 443,
            isHttps: true,
            isReady: true,
            description: 'Primary proxy',
            serverIds: [$serverOne->id],
        ));

        $this->assertDatabaseHas('proxies', [
            'id' => $proxy->id,
            'name' => 'Ru Proxy',
            'host' => 'proxy.example.com',
            'port' => 443,
            'is_ready' => true,
        ]);
        $this->assertSame([$serverOne->id], $proxy->servers->pluck('id')->all());

        $proxy = $service->update($proxy, new ProxyData(
            name: 'Ru Proxy',
            host: 'proxy-updated.example.com',
            port: 8443,
            isHttps: false,
            isReady: false,
            description: null,
            serverIds: [$serverTwo->id],
        ));

        $this->assertSame([$serverTwo->id], $proxy->servers->pluck('id')->all());
        $this->assertDatabaseHas('proxies', [
            'id' => $proxy->id,
            'host' => 'proxy-updated.example.com',
            'port' => 8443,
            'is_https' => false,
            'is_ready' => false,
        ]);
        $this->assertDatabaseMissing('proxy_server', [
            'proxy_id' => $proxy->id,
            'server_id' => $serverOne->id,
        ]);
    }

    public function test_delete_removes_proxy_and_pivot_rows(): void
    {
        $server = $this->createServer('LV1');
        $proxy = Proxy::query()->create([
            'name' => 'Ru Proxy',
            'host' => 'proxy.example.com',
            'port' => 443,
            'is_https' => true,
            'is_ready' => true,
        ]);
        $proxy->servers()->attach($server);

        app(ProxyCrudService::class)->delete($proxy);

        $this->assertDatabaseMissing('proxies', ['id' => $proxy->id]);
        $this->assertDatabaseMissing('proxy_server', [
            'proxy_id' => $proxy->id,
            'server_id' => $server->id,
        ]);
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
