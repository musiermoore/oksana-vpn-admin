<?php

namespace Tests\Feature;

use App\Models\Config;
use App\Models\Server;
use App\Services\WireGuardAgentConfigService;
use App\Services\WireGuardConfigService;
use App\Services\WireGuardConfigServiceFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WireGuardConfigServiceFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_returns_legacy_service_for_wireguard_old_servers(): void
    {
        $server = new Server(['type' => Server::TYPE_WIREGUARD_OLD]);
        $config = new Config(['name' => 'legacy-client']);
        $config->setRelation('server', $server);

        $this->assertInstanceOf(
            WireGuardConfigService::class,
            WireGuardConfigServiceFactory::make($config),
        );
    }

    public function test_factory_returns_agent_service_for_wireguard_agent_servers(): void
    {
        $server = new Server(['type' => Server::TYPE_WIREGUARD]);
        $config = new Config(['name' => 'agent-client']);
        $config->setRelation('server', $server);

        $this->assertInstanceOf(
            WireGuardAgentConfigService::class,
            WireGuardConfigServiceFactory::make($config),
        );
    }
}
