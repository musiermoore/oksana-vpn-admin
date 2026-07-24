<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use App\Models\VlessConfig;
use App\Models\XrayInbound;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisableConfigsOfOverdueDebtorsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-24 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_disables_vless_config_locally_when_inbound_mapping_is_missing(): void
    {
        $server = Server::query()->create([
            'name' => 'VLESS Server',
            'code' => 'VLS',
            'ip' => '10.0.0.10',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_ready' => true,
            'is_active' => true,
            'type' => Server::TYPE_VLESS,
        ]);

        $user = User::query()->create([
            'name' => 'Debtor User',
            'telegram' => '@debtor',
            'telegram_id' => '123456789',
            'balance' => -50,
            'password' => bcrypt('password'),
        ]);

        $inbound = XrayInbound::query()->create([
            'server_id' => $server->id,
            'external_id' => 10,
            'is_active' => true,
            'is_public' => true,
            'params' => ['id' => 10],
        ]);

        $config = VlessConfig::query()->create([
            'server_id' => $server->id,
            'xray_inbound_id' => $inbound->id,
            'user_id' => $user->id,
            'name' => 'debtor_config',
            'is_active' => true,
            'enable' => true,
            'uuid' => '33333333-3333-3333-3333-333333333333',
            'sub_id' => 'sub-333',
            'port' => 443,
            'protocol' => 'vless',
            'type' => 'tcp',
            'encryption' => 'none',
            'security' => 'reality',
            'flow' => 'xtls-rprx-vision',
            'pbk' => 'public-key',
            'fp' => 'chrome',
            'sni' => 'example.com',
            'sid' => 'abcd',
            'spx' => '/',
        ]);

        $inbound->delete();

        $this->artisan('configs:disable-overdue-debtors')
            ->expectsOutputToContain('Skipping remote disable for VLESS config')
            ->assertSuccessful();

        $this->assertFalse((bool) $config->fresh()->enable);
    }
}
