<?php

namespace Tests\Feature;

use App\DTOs\Server\ServerData;
use App\Jobs\InstallWireGuardAgentForServerJob;
use App\Models\Server;
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
            allowedInboundIds: $type === Server::TYPE_VLESS ? [10] : null,
        );
    }
}
