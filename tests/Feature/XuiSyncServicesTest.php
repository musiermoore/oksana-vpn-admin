<?php

namespace Tests\Feature;

use App\Models\ActiveConnection;
use App\Models\Server;
use App\Models\User;
use App\Models\UserServerStat;
use App\Models\VlessConfig;
use App\Services\XuiConnectionSyncService;
use App\Services\XuiUserTrafficSyncService;
use Illuminate\Http\Client\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XuiSyncServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_sync_online_connections_and_aggregate_user_traffic(): void
    {
        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        $server = Server::query()->create([
            'name' => 'Germany',
            'code' => 'DE',
            'ip' => '10.0.0.5',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
            'allowed_inbound_ids' => [10],
        ]);

        $config = VlessConfig::query()->create([
            'server_id' => $server->id,
            'inbound_id' => 10,
            'user_id' => $user->id,
            'name' => 'alice-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'uuid-1',
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

        Http::fake([
            'https://panel.test/' => Http::response(
                '<meta name="csrf-token" content="csrf-token-value">',
                200,
                ['Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly']
            ),
            'https://panel.test/login' => Http::response([], 200, [
                'Set-Cookie' => '3x-ui=test-session; Path=/; HttpOnly',
            ]),
            'https://panel.test/panel/api/clients/onlines' => Http::response([
                'obj' => ['alice-config'],
            ]),
            'https://panel.test/panel/api/clients/ips/alice-config' => Http::response([
                'obj' => [
                    '198.51.100.10 (2026-06-30 10:00:00)',
                    '198.51.100.11 (2026-06-30 10:01:00)',
                ],
            ]),
            'https://panel.test/panel/api/inbounds/list' => Http::response([
                'obj' => [[
                    'id' => 10,
                    'protocol' => 'vless',
                    'port' => 443,
                    'settings' => json_encode([
                        'clients' => [[
                            'id' => $config->uuid,
                            'email' => 'alice-config',
                            'enable' => true,
                        ]],
                    ], JSON_UNESCAPED_SLASHES),
                    'clientStats' => [[
                        'email' => 'alice-config',
                        'up' => 512,
                        'down' => 1024,
                    ]],
                    'streamSettings' => json_encode([
                        'network' => 'tcp',
                        'security' => 'reality',
                    ], JSON_UNESCAPED_SLASHES),
                ]],
            ]),
        ]);

        $touchedUsers = app(XuiConnectionSyncService::class)->syncServer($server);
        $statUsers = app(XuiUserTrafficSyncService::class)->syncServer($server);

        $this->assertSame([$user->id], $touchedUsers);
        $this->assertSame([$user->id], $statUsers);

        $this->assertDatabaseCount('active_connections', 2);
        $this->assertDatabaseHas('active_connections', [
            'user_id' => $user->id,
            'config_id' => $config->id,
            'config_type' => ActiveConnection::CONFIG_TYPE_VLESS,
            'ip' => '198.51.100.10',
        ]);

        $this->assertDatabaseHas('user_server_stats', [
            'user_id' => $user->id,
            'server_id' => $server->id,
            'upload_bytes' => 512,
            'download_bytes' => 1024,
        ]);

        $stat = UserServerStat::query()->where('user_id', $user->id)->where('server_id', $server->id)->first();

        $this->assertNotNull($stat);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://panel.test/panel/api/clients/onlines');
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://panel.test/panel/api/clients/ips/alice-config');
    }
}
