<?php

namespace Tests\Feature;

use App\Models\ActiveConnection;
use App\Models\BlockedConfig;
use App\Models\Server;
use App\Models\User;
use App\Models\VlessConfig;
use App\Services\DeviceLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeviceLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_blocks_latest_connection_config_when_user_exceeds_device_limit(): void
    {
        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
            'max_devices' => 1,
        ]);

        $server = Server::query()->create([
            'name' => 'Germany',
            'code' => 'DE',
            'ip' => '10.0.0.5',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'panel_api_version' => Server::PANEL_API_V3_2_8,
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
            'allowed_inbound_ids' => [10],
        ]);

        $olderConfig = VlessConfig::query()->create([
            'server_id' => $server->id,
            'inbound_id' => 10,
            'user_id' => $user->id,
            'name' => 'alice-old',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'uuid-old',
            'port' => 443,
            'protocol' => 'vless',
            'type' => 'tcp',
            'encryption' => 'none',
            'security' => 'reality',
            'pbk' => 'public-key',
            'fp' => 'chrome',
            'sni' => 'example.com',
            'sid' => 'abcd',
            'spx' => '/',
        ]);

        $newerConfig = VlessConfig::query()->create([
            'server_id' => $server->id,
            'inbound_id' => 10,
            'user_id' => $user->id,
            'name' => 'alice-new',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'uuid-new',
            'port' => 443,
            'protocol' => 'vless',
            'type' => 'tcp',
            'encryption' => 'none',
            'security' => 'reality',
            'pbk' => 'public-key',
            'fp' => 'chrome',
            'sni' => 'example.com',
            'sid' => 'abcd',
            'spx' => '/',
        ]);

        ActiveConnection::query()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'config_type' => ActiveConnection::CONFIG_TYPE_VLESS,
            'config_id' => $olderConfig->id,
            'protocol' => 'vless',
            'ip' => '198.51.100.10',
            'first_seen' => now()->subMinutes(5),
            'last_seen' => now(),
        ]);

        ActiveConnection::query()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'config_type' => ActiveConnection::CONFIG_TYPE_VLESS,
            'config_id' => $newerConfig->id,
            'protocol' => 'vless',
            'ip' => '198.51.100.11',
            'first_seen' => now()->subSeconds(20),
            'last_seen' => now(),
        ]);

        Http::fake([
            'https://panel.test/' => Http::response(
                '<meta name="csrf-token" content="csrf-token-value">',
                200,
                ['Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly']
            ),
            'https://panel.test/login' => Http::response([], 200, [
                'Set-Cookie' => '3x-ui=test-session; Path=/; HttpOnly',
            ]),
            'https://panel.test/panel/api/inbounds/list' => Http::response([
                'obj' => [[
                    'id' => 10,
                    'protocol' => 'vless',
                    'port' => 443,
                    'settings' => json_encode([
                        'clients' => [
                            [
                                'id' => 'uuid-old',
                                'email' => 'alice-old',
                                'enable' => true,
                            ],
                            [
                                'id' => 'uuid-new',
                                'email' => 'alice-new',
                                'enable' => true,
                            ],
                        ],
                    ], JSON_UNESCAPED_SLASHES),
                    'streamSettings' => json_encode([
                        'network' => 'tcp',
                        'security' => 'reality',
                    ], JSON_UNESCAPED_SLASHES),
                ]],
            ]),
            'https://panel.test/panel/api/clients/update/alice-new?inboundIds=10' => Http::response([
                'success' => true,
            ]),
        ]);

        app(DeviceLimitService::class)->enforceForUser($user->id);

        $this->assertDatabaseHas('blocked_configs', [
            'user_id' => $user->id,
            'config_type' => ActiveConnection::CONFIG_TYPE_VLESS,
            'config_id' => $newerConfig->id,
        ]);

        $this->assertFalse($newerConfig->fresh()->enable);
        $this->assertTrue($olderConfig->fresh()->enable);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://panel.test/panel/api/clients/update/alice-new?inboundIds=10'
                && ($request['enable'] ?? null) === false;
        });
    }

    public function test_service_unblocks_config_after_active_devices_drop_within_limit(): void
    {
        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
            'max_devices' => 1,
        ]);

        $server = Server::query()->create([
            'name' => 'Germany',
            'code' => 'DE',
            'ip' => '10.0.0.5',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'panel_api_version' => Server::PANEL_API_V3_2_8,
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
            'allowed_inbound_ids' => [10],
        ]);

        $config = VlessConfig::query()->create([
            'server_id' => $server->id,
            'inbound_id' => 10,
            'user_id' => $user->id,
            'name' => 'alice-new',
            'is_active' => true,
            'enable' => false,
            'uuid' => 'uuid-new',
            'port' => 443,
            'protocol' => 'vless',
            'type' => 'tcp',
            'encryption' => 'none',
            'security' => 'reality',
            'pbk' => 'public-key',
            'fp' => 'chrome',
            'sni' => 'example.com',
            'sid' => 'abcd',
            'spx' => '/',
        ]);

        BlockedConfig::query()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'config_type' => ActiveConnection::CONFIG_TYPE_VLESS,
            'config_id' => $config->id,
            'reason' => 'limit exceeded',
            'blocked_until' => now()->subMinute(),
        ]);

        ActiveConnection::query()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'config_type' => ActiveConnection::CONFIG_TYPE_VLESS,
            'config_id' => $config->id,
            'protocol' => 'vless',
            'ip' => '198.51.100.10',
            'first_seen' => now()->subMinutes(5),
            'last_seen' => now()->subMinutes(3),
        ]);

        Http::fake([
            'https://panel.test/' => Http::response(
                '<meta name="csrf-token" content="csrf-token-value">',
                200,
                ['Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly']
            ),
            'https://panel.test/login' => Http::response([], 200, [
                'Set-Cookie' => '3x-ui=test-session; Path=/; HttpOnly',
            ]),
            'https://panel.test/panel/api/inbounds/list' => Http::response([
                'obj' => [[
                    'id' => 10,
                    'protocol' => 'vless',
                    'port' => 443,
                    'settings' => json_encode([
                        'clients' => [[
                            'id' => 'uuid-new',
                            'email' => 'alice-new',
                            'enable' => false,
                        ]],
                    ], JSON_UNESCAPED_SLASHES),
                    'streamSettings' => json_encode([
                        'network' => 'tcp',
                        'security' => 'reality',
                    ], JSON_UNESCAPED_SLASHES),
                ]],
            ]),
            'https://panel.test/panel/api/clients/update/alice-new?inboundIds=10' => Http::response([
                'success' => true,
            ]),
        ]);

        app(DeviceLimitService::class)->releaseExpiredBlocks();

        $this->assertDatabaseMissing('blocked_configs', [
            'config_type' => ActiveConnection::CONFIG_TYPE_VLESS,
            'config_id' => $config->id,
        ]);
        $this->assertTrue($config->fresh()->enable);
    }
}
