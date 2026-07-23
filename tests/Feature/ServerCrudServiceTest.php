<?php

namespace Tests\Feature;

use App\DTOs\Server\ServerData;
use App\Jobs\InstallWireGuardAgentForServerJob;
use App\Models\Server;
use App\Models\XrayInbound;
use App\Services\Crud\ServerCrudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerCrudServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_wireguard_agent_server_dispatches_install_job(): void
    {
        Queue::fake();

        $server = app(ServerCrudService::class)->create($this->makeServerData(Server::TYPE_WIREGUARD));

        Queue::assertPushed(InstallWireGuardAgentForServerJob::class, function (InstallWireGuardAgentForServerJob $job) use ($server) {
            return $job->serverId === $server->id;
        });
    }

    public function test_creating_legacy_or_vless_server_does_not_dispatch_install_job(): void
    {
        Queue::fake();

        app(ServerCrudService::class)->create($this->makeServerData(Server::TYPE_WIREGUARD_OLD, 'OLD'));
        app(ServerCrudService::class)->create($this->makeServerData(Server::TYPE_VLESS, 'VLS'));

        Queue::assertNotPushed(InstallWireGuardAgentForServerJob::class);
    }

    public function test_enable_and_disable_update_server_activity_flag(): void
    {
        $service = app(ServerCrudService::class);
        $server = $service->create($this->makeServerData(Server::TYPE_VLESS, 'VLS'));

        $this->assertTrue((bool) $server->is_active);

        $server = $service->disable($server);
        $this->assertFalse((bool) $server->is_active);

        $server = $service->enable($server);
        $this->assertTrue((bool) $server->is_active);
    }

    public function test_update_syncs_server_inbound_flags(): void
    {
        $service = app(ServerCrudService::class);
        $server = $service->create($this->makeServerData(Server::TYPE_VLESS, 'VLS'));

        $inbound = XrayInbound::query()->create([
            'server_id' => $server->id,
            'external_id' => 10,
            'is_active' => true,
            'is_public' => true,
            'params' => ['id' => 10, 'protocol' => 'vless', 'remark' => 'Main'],
        ]);

        $service->update($server, new ServerData(
            name: 'Server VLS',
            code: 'VLS',
            ip: '10.0.0.1',
            type: Server::TYPE_VLESS,
            isHttps: true,
            linkHost: null,
            panelLink: 'https://agent.test',
            panelUsername: 'admin',
            panelPassword: 'secret',
            panelApiVersion: Server::PANEL_API_V2_9,
            appPath: '/opt/app',
            sshPrivateKey: null,
            sshPublicKey: null,
            isActive: true,
            isReady: false,
            hideConfigsForNonAdmins: false,
            inbounds: [[
                'id' => $inbound->id,
                'is_active' => false,
                'is_public' => false,
            ]],
        ));

        $this->assertDatabaseHas('xray_inbounds', [
            'id' => $inbound->id,
            'is_active' => false,
            'is_public' => false,
        ]);
    }

    private function makeServerData(string $type, string $code = 'WGA'): ServerData
    {
        return new ServerData(
            name: 'Server '.$code,
            code: $code,
            ip: '10.0.0.1',
            type: $type,
            isHttps: true,
            linkHost: null,
            panelLink: 'https://agent.test',
            panelUsername: 'admin',
            panelPassword: 'secret',
            panelApiVersion: Server::PANEL_API_V2_9,
            appPath: '/opt/app',
            sshPrivateKey: null,
            sshPublicKey: null,
            isActive: true,
            isReady: false,
            hideConfigsForNonAdmins: false,
        );
    }
}
