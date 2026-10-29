<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Proxy;
use App\Models\ProxyGroup;
use App\Models\Server;
use App\Models\User;
use App\Models\XrayInbound;
use App\Services\ProxyGroupConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProxyGroupConfigurationPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_proxy_group_page_and_creation_are_admin_only(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)
            ->post(route('proxy-groups.store'), ['name' => 'Nordic cascade'])
            ->assertRedirect(route('proxy-groups.index'));
        $this->assertDatabaseHas('proxy_groups', ['name' => 'Nordic cascade']);

        $this->actingAs($user)->get(route('proxy-groups.index'))->assertForbidden();
    }

    public function test_preview_shows_cascade_and_per_server_haproxy_configuration(): void
    {
        $destinationServer = $this->createServer('DEST');
        $hopServer = $this->createServer('HOP1');
        $inbound = XrayInbound::query()->create([
            'server_id' => $destinationServer->id,
            'external_id' => 17,
            'is_active' => true,
            'is_public' => true,
            'params' => ['id' => 17, 'protocol' => 'vless', 'port' => 13708],
        ]);
        $group = ProxyGroup::query()->create(['name' => 'Finland cascade']);
        $proxy = Proxy::query()->create([
            'name' => 'Finland',
            'proxy_group_id' => $group->id,
            'server_id' => $destinationServer->id,
            'xray_inbound_id' => $inbound->id,
            'sort_order' => 0,
            'is_https' => true,
            'is_ready' => false,
            'hide_main_node_name' => false,
        ]);
        $proxy->hosts()->create(['host' => 'proxy-fin.example.com', 'port' => 444, 'sort' => 0, 'is_active' => true, 'is_https' => false]);
        $proxy->hosts()->create([
            'host' => '194.164.168.89',
            'port' => 444,
            'sort' => 1,
            'is_active' => true,
            'is_https' => false,
            'proxy_server_id' => $hopServer->id,
        ]);

        $preview = app(ProxyGroupConfigurationService::class)->preview($group);

        $this->assertSame('http://proxy-fin.example.com:444 → http://194.164.168.89:444 → https://dest.example.com:13708', $preview['flow'][0]['path']);
        $this->assertSame($hopServer->id, $preview['servers'][0]['id']);
        $this->assertStringContainsString('bind *:444', $preview['servers'][0]['frontend_config']);
        $this->assertStringContainsString('server finland_1 dest.example.com:13708 check ssl verify none', $preview['servers'][0]['backend_config']);
        $this->assertStringContainsString('systemctl edit haproxy', implode("\n", $preview['instructions']));
        $this->assertStringContainsString('/etc/haproxy/backends/backend.cfg', $preview['instructions'][2]);
    }

    private function createServer(string $code): Server
    {
        return Server::query()->create([
            'name' => 'Server '.$code,
            'code' => $code,
            'ip' => '10.0.0.1',
            'link_host' => strtolower($code).'.example.com',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
            'is_https' => true,
        ]);
    }
}
