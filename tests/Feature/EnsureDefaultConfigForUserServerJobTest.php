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
            'https://panel.test/csrf-token' => Http::response([
                'token' => 'csrf-token-value',
            ], 200, ['Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly']),
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

    public function test_job_claims_orphan_vless_config_for_user_when_local_row_exists_without_user_id(): void
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

        $orphanUuid = '33333333-3333-3333-3333-333333333333';
        $orphanName = 'alice_ready_vless_1';

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => null,
            'inbound_id' => 10,
            'name' => $orphanName,
            'is_active' => true,
            'enable' => false,
            'uuid' => $orphanUuid,
            'port' => 443,
            'type' => 'tcp',
            'protocol' => 'vless',
        ]);

        Http::fake([
            'https://panel.test/csrf-token' => Http::response([
                'token' => 'csrf-token-value',
            ], 200, ['Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly']),
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
                    'settings' => [
                        'clients' => [[
                            'id' => $orphanUuid,
                            'email' => $orphanName,
                            'enable' => true,
                            'subId' => 'sub-id-1',
                        ]],
                    ],
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
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertDatabaseHas('vless_configs', [
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 10,
            'name' => $orphanName,
            'uuid' => $orphanUuid,
            'enable' => true,
        ]);

        $this->assertDatabaseCount('vless_configs', 1);
    }

    public function test_job_claims_orphan_vless_config_even_when_panel_does_not_return_clients_list(): void
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

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => null,
            'inbound_id' => 10,
            'name' => 'alice_ready_vless_1',
            'is_active' => true,
            'enable' => true,
            'uuid' => '44444444-4444-4444-4444-444444444444',
            'port' => 443,
            'type' => 'tcp',
            'protocol' => 'vless',
        ]);

        Http::fake([
            'https://panel.test/csrf-token' => Http::response([
                'token' => 'csrf-token-value',
            ], 200, ['Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly']),
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
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertDatabaseHas('vless_configs', [
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 10,
            'name' => 'alice_ready_vless_1',
            'uuid' => '44444444-4444-4444-4444-444444444444',
        ]);
    }

    public function test_job_claims_existing_panel_client_without_creating_duplicate(): void
    {
        $server = Server::query()->create([
            'name' => 'Ready Mixed',
            'code' => 'RMX',
            'ip' => '10.0.0.9',
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
            'https://panel.test/csrf-token' => Http::response([
                'token' => 'csrf-token-value',
            ], 200, ['Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly']),
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
                    'settings' => [
                        'clients' => [[
                            'id' => 'existing-vless-uuid',
                            'email' => 'alice_ready_mixed_1',
                            'enable' => true,
                            'subId' => 'existing-sub-id',
                            'password' => 'existing-password',
                            'auth' => 'existing-auth',
                        ]],
                    ],
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
                    'protocol' => 'hysteria',
                    'port' => 8443,
                    'settings' => [
                        'clients' => [[
                            'id' => 'existing-hysteria-uuid',
                            'email' => 'alice_ready_mixed_2',
                            'auth' => 'existing-hysteria-auth',
                            'enable' => true,
                        ]],
                    ],
                    'streamSettings' => json_encode([
                        'network' => 'hysteria',
                        'security' => 'tls',
                        'tlsSettings' => [
                            'serverName' => 'hy.example.com',
                            'alpn' => ['h3'],
                            'settings' => [
                                'fingerprint' => 'firefox',
                            ],
                        ],
                    ], JSON_UNESCAPED_SLASHES),
                ]],
            ]),
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertDatabaseHas('vless_configs', [
            'user_id' => $user->id,
            'server_id' => $server->id,
            'inbound_id' => 10,
            'name' => 'alice_ready_mixed_1',
            'uuid' => 'existing-vless-uuid',
            'protocol' => 'vless',
        ]);

        $this->assertDatabaseHas('vless_configs', [
            'user_id' => $user->id,
            'server_id' => $server->id,
            'inbound_id' => 11,
            'name' => 'alice_ready_mixed_2',
            'uuid' => 'existing-hysteria-uuid',
            'auth' => 'existing-hysteria-auth',
            'protocol' => 'hysteria',
        ]);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/addClient'));
    }

    public function test_job_keeps_uuid_from_clients_list_uuid_field_instead_of_numeric_panel_id(): void
    {
        $server = Server::query()->create([
            'name' => 'Ready VLESS',
            'code' => 'RVL',
            'ip' => '10.0.0.2',
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
            'telegram_id' => '123456789',
            'join_at' => now()->toDateString(),
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 10,
        ]);

        Http::fake([
            'https://panel.test/csrf-token' => Http::response([
                'token' => 'csrf-token-value',
            ], 200, ['Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly']),
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
                            'id' => '44444444-4444-4444-4444-444444444444',
                            'email' => '123456789_ready_vless_1',
                            'enable' => true,
                            'subId' => 'sub-id-1',
                            'password' => 'password-1',
                            'auth' => 'auth-1',
                        ]],
                    ], JSON_UNESCAPED_SLASHES),
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
            'https://panel.test/panel/api/clients/list' => Http::response([
                'obj' => [[
                    'id' => 1218,
                    'uuid' => '44444444-4444-4444-4444-444444444444',
                    'email' => '123456789_ready_vless_1',
                    'subId' => 'sub-id-1',
                    'password' => 'password-1',
                    'auth' => 'auth-1',
                    'enable' => true,
                    'inboundIds' => [10],
                ]],
            ]),
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertDatabaseHas('vless_configs', [
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 10,
            'name' => '123456789_ready_vless_1',
            'uuid' => '44444444-4444-4444-4444-444444444444',
        ]);
    }

    public function test_job_continues_creating_other_inbounds_when_one_inbound_fails(): void
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
            'allowed_inbound_ids' => [10, 11, 12],
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123456789',
            'join_at' => now()->toDateString(),
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 10,
        ]);

        Http::fake([
            'https://panel.test/csrf-token' => Http::response([
                'token' => 'csrf-token-value',
            ], 200, ['Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly']),
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
                    'protocol' => 'hysteria',
                    'port' => 8443,
                    'streamSettings' => json_encode([
                        'network' => 'hysteria',
                        'security' => 'tls',
                        'tlsSettings' => [
                            'serverName' => 'hy.example.com',
                            'alpn' => ['h3'],
                            'settings' => [
                                'fingerprint' => 'firefox',
                            ],
                        ],
                    ], JSON_UNESCAPED_SLASHES),
                ], [
                    'id' => 12,
                    'protocol' => 'vless',
                    'port' => 9443,
                    'streamSettings' => json_encode([
                        'network' => 'xhttp',
                        'security' => 'reality',
                        'realitySettings' => [
                            'settings' => [
                                'publicKey' => 'public-key-2',
                                'fingerprint' => 'edge',
                            ],
                            'serverNames' => ['example.net'],
                            'shortIds' => ['efgh'],
                        ],
                        'xhttpSettings' => [
                            'path' => '/search',
                            'mode' => 'auto',
                            'extra' => [
                                'xPaddingBytes' => '100-500',
                            ],
                        ],
                    ], JSON_UNESCAPED_SLASHES),
                ]],
            ]),
            'https://panel.test/panel/api/inbounds/addClient' => function (Request $request) {
                $payload = json_decode((string) $request['settings'], true);
                $email = $payload['clients'][0]['email'] ?? '';

                if ($request['id'] === 11 || str_contains((string) $email, '_ready_vless_2')) {
                    return Http::response(['message' => 'boom'], 500);
                }

                return Http::response(['success' => true], 200);
            },
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertDatabaseHas('vless_configs', [
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 10,
        ]);

        $this->assertDatabaseMissing('vless_configs', [
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 11,
        ]);

        $this->assertDatabaseHas('vless_configs', [
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 12,
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
            'https://panel.test/csrf-token' => Http::response([
                'token' => 'csrf-token-value',
            ], 200, ['Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly']),
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

    public function test_job_uses_telegram_id_as_config_name_prefix(): void
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
            'telegram_id' => '123456789',
            'join_at' => now()->toDateString(),
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 10,
        ]);

        Http::fake([
            'https://panel.test/csrf-token' => Http::response([
                'token' => 'csrf-token-value',
            ], 200, ['Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly']),
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

        $config = VlessConfig::query()
            ->where('server_id', $server->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertStringStartsWith('123456789_ready_vless_', $config->name);
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
            'https://panel.test/csrf-token' => Http::response([
                'token' => 'csrf-token-value',
            ], 200, ['Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly']),
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
            'https://panel.test/csrf-token' => Http::response([
                'token' => 'csrf-token-value',
            ], 200, ['Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly']),
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
            'https://panel.test/csrf-token' => Http::response([
                'token' => 'csrf-token-value',
            ], 200, ['Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly']),
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
            'https://panel.test/csrf-token' => Http::response([
                'token' => 'csrf-token-value',
            ], 200, ['Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly']),
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
                        'network' => 'hysteria',
                        'hysteriaSettings' => [
                            'version' => 2,
                        ],
                        'security' => 'tls',
                        'tlsSettings' => [
                            'serverName' => 'lv.oksana1984.ru',
                            'alpn' => ['h2', 'http/1.1', 'h3'],
                            'settings' => [
                                'fingerprint' => 'firefox',
                            ],
                        ],
                        'finalmask' => [
                            'udp' => [[
                                'type' => 'salamander',
                                'settings' => [
                                    'password' => 'rva44wfs935cbf5s',
                                ],
                            ]],
                        ],
                    ], JSON_UNESCAPED_SLASHES),
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
