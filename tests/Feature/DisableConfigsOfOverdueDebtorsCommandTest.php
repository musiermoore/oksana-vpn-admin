<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\VlessConfig;
use App\Models\XrayInbound;
use App\Services\Crud\VlessConfigCrudService;
use Carbon\Carbon;
use Exception;
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

    public function test_command_keeps_running_when_remote_disable_throws_non_runtime_exception(): void
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

        $this->mock(VlessConfigCrudService::class, function ($mock): void {
            $mock->shouldReceive('disable')
                ->once()
                ->andThrow(new Exception('Timed out contacting upstream server.'));
        });

        $this->artisan('configs:disable-overdue-debtors')
            ->expectsOutputToContain('Failed to disable VLESS config')
            ->assertSuccessful();

        $this->assertTrue((bool) $config->fresh()->enable);
    }

    public function test_command_recalculates_stale_balance_before_access_check(): void
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
            'name' => 'Active User',
            'telegram' => '@active',
            'telegram_id' => '999888777',
            'balance' => -150,
            'password' => bcrypt('password'),
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-08-10',
            'price' => 150,
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => 259,
            'is_approved' => true,
            'description' => 'Approved deposit',
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_SUBSCRIPTION),
            'amount' => -150,
            'is_approved' => true,
            'description' => 'Subscription charge',
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
            'name' => 'active_config',
            'is_active' => true,
            'enable' => true,
            'uuid' => '44444444-4444-4444-4444-444444444444',
            'sub_id' => 'sub-444',
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

        $this->artisan('configs:disable-overdue-debtors', ['user_id' => $user->id])
            ->assertSuccessful();

        $this->assertSame(109.0, $user->fresh()->balance);
        $this->assertTrue((bool) $config->fresh()->enable);
    }

    public function test_command_keeps_config_enabled_for_active_subscription_even_with_negative_balance(): void
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
            'name' => 'Subscriber User',
            'telegram' => '@subscriber',
            'telegram_id' => '555666777',
            'balance' => -150,
            'password' => bcrypt('password'),
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-08-10',
            'price' => 150,
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
            'name' => 'subscriber_config',
            'is_active' => true,
            'enable' => true,
            'uuid' => '55555555-5555-5555-5555-555555555555',
            'sub_id' => 'sub-555',
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

        $this->artisan('configs:disable-overdue-debtors', ['user_id' => $user->id])
            ->assertSuccessful();

        $this->assertTrue((bool) $config->fresh()->enable);
    }
}
