<?php

namespace Tests\Feature;

use App\Jobs\EnsureDefaultConfigForUserServerJob;
use App\Models\Server;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\VlessConfig;
use Illuminate\Http\Client\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnsureDefaultConfigForUserServerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_one_wireguard_config_for_ready_server(): void
    {
        $server = Server::query()->create([
            'name' => 'Ready WG',
            'code' => 'RWG',
            'ip' => '10.0.0.1',
            'app_path' => '/opt/app',
            'is_ready' => true,
            'type' => Server::TYPE_WIREGUARD_OLD,
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 10,
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertDatabaseHas('configs', [
            'user_id' => $user->id,
            'server_id' => $server->id,
            'name' => 'alice_RWG',
        ]);
    }

    public function test_job_does_not_create_config_for_inactive_server(): void
    {
        $server = Server::query()->create([
            'name' => 'Inactive WG',
            'code' => 'IWG',
            'ip' => '10.0.0.10',
            'app_path' => '/opt/app',
            'is_active' => false,
            'is_ready' => true,
            'type' => Server::TYPE_WIREGUARD_OLD,
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 10,
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertDatabaseCount('configs', 0);
    }

    public function test_job_creates_one_wireguard_agent_config_for_ready_server(): void
    {
        $server = Server::query()->create([
            'name' => 'Ready WG Agent',
            'code' => 'RWA',
            'ip' => '10.0.0.9',
            'app_path' => '/opt/app',
            'panel_link' => 'https://agent.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_ready' => true,
            'type' => Server::TYPE_WIREGUARD,
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 10,
        ]);

        Http::fake([
            'https://agent.test/status' => Http::response([
                'installed' => true,
            ]),
            'https://agent.test/clients' => Http::response([
                'success' => true,
            ]),
            'https://agent.test/clients/*/config' => Http::response('[Interface]'."\n".'Address = 10.0.0.2/32'),
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $config = $user->configs()->where('server_id', $server->id)->first();

        $this->assertNotNull($config);
        $this->assertTrue(file_exists($config->path));
        Http::assertSentCount(3);
    }

    public function test_job_creates_one_vless_config_for_ready_server(): void
    {
        $server = Server::query()->create([
            'name' => 'Ready VLESS',
            'code' => 'RVL',
            'ip' => '10.0.0.2',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
            'allowed_inbound_ids' => [10],
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 10,
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
                    'streamSettings' => json_encode([
                        'network' => 'tcp',
                        'security' => 'reality',
                        'realitySettings' => [
                            'settings' => [
                                'publicKey' => 'public-key',
                                'fingerprint' => 'chrome',
                            ],
                            'serverNames' => ['example.com'],
                            'shortIds' => ['abcd'],
                        ],
                    ], JSON_UNESCAPED_SLASHES),
                ]],
            ]),
            'https://panel.test/panel/api/inbounds/addClient' => Http::response([
                'success' => true,
            ]),
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertDatabaseHas('vless_configs', [
            'user_id' => $user->id,
            'server_id' => $server->id,
            'port' => 443,
            'type' => 'tcp',
            'security' => 'reality',
            'pbk' => 'public-key',
            'fp' => 'chrome',
            'sni' => 'example.com',
            'sid' => 'abcd',
            'enable' => true,
        ]);
    }

    public function test_job_does_not_create_vless_config_when_auto_pull_types_are_not_configured(): void
    {
        $server = Server::query()->create([
            'name' => 'Ready VLESS',
            'code' => 'RVL',
            'ip' => '10.0.0.2',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 10,
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertDatabaseCount('vless_configs', 0);
    }

    public function test_job_creates_configs_for_all_allowed_inbounds_including_ws(): void
    {
        $server = Server::query()->create([
            'name' => 'Ready VLESS',
            'code' => 'RVL',
            'ip' => '10.0.0.2',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
            'allowed_inbound_ids' => [10, 11],
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 10,
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
                    'streamSettings' => json_encode([
                        'network' => 'tcp',
                        'security' => 'reality',
                        'realitySettings' => [
                            'settings' => [
                                'publicKey' => 'public-key',
                                'fingerprint' => 'chrome',
                            ],
                            'serverNames' => ['example.com'],
                            'shortIds' => ['abcd'],
                        ],
                    ], JSON_UNESCAPED_SLASHES),
                ], [
                    'id' => 11,
                    'protocol' => 'vless',
                    'port' => 8443,
                    'streamSettings' => json_encode([
                        'network' => 'ws',
                        'security' => 'tls',
                        'tlsSettings' => [
                            'serverName' => 'ws.example.com',
                        ],
                        'wsSettings' => [
                            'path' => '/socket',
                            'headers' => [
                                'Host' => 'cdn.example.com',
                            ],
                        ],
                    ], JSON_UNESCAPED_SLASHES),
                ]],
            ]),
            'https://panel.test/panel/api/inbounds/addClient' => Http::response([
                'success' => true,
            ]),
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertDatabaseHas('vless_configs', [
            'user_id' => $user->id,
            'server_id' => $server->id,
            'inbound_id' => 10,
            'type' => 'tcp',
            'security' => 'reality',
            'flow' => 'xtls-rprx-vision',
        ]);

        $this->assertDatabaseHas('vless_configs', [
            'user_id' => $user->id,
            'server_id' => $server->id,
            'inbound_id' => 11,
            'type' => 'ws',
            'security' => 'tls',
            'host' => 'cdn.example.com',
            'path' => '/socket',
            'sni' => 'ws.example.com',
            'flow' => null,
        ]);
    }

    public function test_job_creates_trojan_config_for_allowed_trojan_inbound(): void
    {
        $server = Server::query()->create([
            'name' => 'Ready Trojan',
            'code' => 'RTJ',
            'ip' => '10.0.0.20',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
            'allowed_inbound_ids' => [12],
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 10,
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
                    'id' => 12,
                    'protocol' => 'trojan',
                    'port' => 443,
                    'streamSettings' => json_encode([
                        'network' => 'tcp',
                        'security' => 'tls',
                        'tlsSettings' => [
                            'serverName' => 'trojan.example.com',
                        ],
                    ], JSON_UNESCAPED_SLASHES),
                ]],
            ]),
            'https://panel.test/panel/api/inbounds/addClient' => Http::response([
                'success' => true,
            ]),
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertDatabaseHas('vless_configs', [
            'user_id' => $user->id,
            'server_id' => $server->id,
            'inbound_id' => 12,
            'port' => 443,
            'protocol' => 'trojan',
            'type' => 'tcp',
            'security' => 'tls',
            'sni' => 'trojan.example.com',
            'enable' => true,
        ]);
    }

    public function test_job_falls_back_to_legacy_add_client_endpoint_when_modern_route_returns_404(): void
    {
        $server = Server::query()->create([
            'name' => 'Legacy VLESS',
            'code' => 'LVL',
            'ip' => '10.0.0.3',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
            'allowed_inbound_ids' => [10],
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 10,
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
                    'streamSettings' => json_encode([
                        'network' => 'tcp',
                        'security' => 'reality',
                        'realitySettings' => [
                            'settings' => [
                                'publicKey' => 'public-key',
                                'fingerprint' => 'chrome',
                            ],
                            'serverNames' => ['example.com'],
                            'shortIds' => ['abcd'],
                        ],
                    ], JSON_UNESCAPED_SLASHES),
                ]],
            ]),
            'https://panel.test/panel/api/inbounds/addClient' => Http::response([], 404),
            'https://panel.test/panel/inbound/addClient' => Http::response([
                'success' => true,
            ]),
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertDatabaseHas('vless_configs', [
            'user_id' => $user->id,
            'server_id' => $server->id,
            'inbound_id' => 10,
            'security' => 'reality',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://panel.test/panel/api/inbounds/addClient';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://panel.test/panel/inbound/addClient';
        });
    }

    public function test_job_creates_vless_config_via_v3_client_api(): void
    {
        $server = Server::query()->create([
            'name' => 'Modern VLESS',
            'code' => 'MVL',
            'ip' => '10.0.0.4',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'panel_api_version' => Server::PANEL_API_V3_2_8,
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
            'allowed_inbound_ids' => [10],
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 10,
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
                    'streamSettings' => json_encode([
                        'network' => 'tcp',
                        'security' => 'reality',
                        'realitySettings' => [
                            'settings' => [
                                'publicKey' => 'public-key',
                                'fingerprint' => 'chrome',
                            ],
                            'serverNames' => ['example.com'],
                            'shortIds' => ['abcd'],
                        ],
                    ], JSON_UNESCAPED_SLASHES),
                ]],
            ]),
            'https://panel.test/panel/api/clients/add' => Http::response([
                'success' => true,
            ]),
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertDatabaseHas('vless_configs', [
            'user_id' => $user->id,
            'server_id' => $server->id,
            'inbound_id' => 10,
            'security' => 'reality',
        ]);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://panel.test/panel/api/clients/add'
                && $request['inboundIds'] === [10]
                && ($request['client']['flow'] ?? null) === 'xtls-rprx-vision'
                && ($request['client']['security'] ?? null) === 'auto';
        });
    }

    public function test_job_refreshes_existing_hysteria_config_from_panel_client_settings(): void
    {
        $server = Server::query()->create([
            'name' => 'Ready Hysteria',
            'code' => 'RHY',
            'ip' => '10.0.0.5',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
            'allowed_inbound_ids' => [10],
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 10,
        ]);

        $config = VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 10,
            'name' => '@alice',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'hysteria-uuid',
            'auth' => 'old-auth',
            'port' => 59885,
            'protocol' => 'hysteria',
            'type' => 'udp',
            'encryption' => 'none',
            'security' => 'tls',
            'sni' => 'lv.oksana1984.ru',
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
                    'protocol' => 'hysteria',
                    'port' => 59885,
                    'settings' => [
                        'clients' => [[
                            'id' => 'hysteria-uuid',
                            'email' => '@alice',
                            'auth' => 'xrp11ixkmlsebrwe',
                            'enable' => true,
                        ]],
                        'obfs' => [
                            'type' => 'salamander',
                            'password' => 'rva44wfs935cbf5s',
                        ],
                    ],
                    'streamSettings' => json_encode([
                        'network' => 'udp',
                        'security' => 'tls',
                        'tlsSettings' => [
                            'serverName' => 'lv.oksana1984.ru',
                            'alpn' => ['h2', 'http/1.1', 'h3'],
                        ],
                    ], JSON_UNESCAPED_SLASHES),
                    'fp' => 'firefox',
                ]],
            ]),
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertDatabaseHas('vless_configs', [
            'id' => $config->id,
            'auth' => 'xrp11ixkmlsebrwe',
            'alpn' => 'h2,http/1.1,h3',
            'fp' => 'firefox',
            'obfs' => 'salamander',
            'obfs_password' => 'rva44wfs935cbf5s',
            'security' => 'tls',
            'sni' => 'lv.oksana1984.ru',
        ]);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/addClient'));
    }
}
