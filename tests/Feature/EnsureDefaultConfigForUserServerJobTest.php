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
        $server = $this->createVlessServer([
            'name' => 'Ready VLESS',
            'code' => 'RVL',
            'ip' => '10.0.0.2',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ], [10]);

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
            'https://panel.test/panel/api/clients/list' => Http::response([
                'obj' => [[
                    'id' => 'admin-private-client-uuid',
                    'email' => 'admin_private_vless_1',
                    'enable' => true,
                    'inboundIds' => [10],
                ]],
            ]),
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $config = VlessConfig::query()
            ->where('user_id', $user->id)
            ->where('server_id', $server->id)
            ->with('xrayInbound:id,external_id')
            ->firstOrFail();

        $this->assertSame(443, $config->port);
        $this->assertSame('tcp', $config->type);
        $this->assertTrue((bool) $config->enable);
        $this->assertSame(10, $config->getResolvedInboundId());
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

        $this->assertVlessConfigHas([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'name' => $orphanName,
            'uuid' => $orphanUuid,
            'enable' => true,
        ]);
        $this->assertVlessConfigUsesInbound([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'name' => $orphanName,
            'uuid' => $orphanUuid,
            'enable' => true,
        ], 10);

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

        $this->assertVlessConfigHas([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'name' => 'alice_ready_vless_1',
            'uuid' => '44444444-4444-4444-4444-444444444444',
        ]);
        $this->assertVlessConfigUsesInbound([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'name' => 'alice_ready_vless_1',
            'uuid' => '44444444-4444-4444-4444-444444444444',
        ], 10);
    }

    public function test_job_claims_existing_panel_client_without_creating_duplicate(): void
    {
        $server = $this->createVlessServer([
            'name' => 'Ready Mixed',
            'code' => 'RMX',
            'ip' => '10.0.0.9',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ], [10, 11]);

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

        $this->assertVlessConfigHas([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'name' => 'alice_ready_mixed_1',
            'uuid' => 'existing-vless-uuid',
            'protocol' => 'vless',
        ]);
        $this->assertVlessConfigUsesInbound([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'name' => 'alice_ready_mixed_1',
            'uuid' => 'existing-vless-uuid',
            'protocol' => 'vless',
        ], 10);

        $this->assertVlessConfigHas([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'name' => 'alice_ready_mixed_2',
            'uuid' => 'existing-hysteria-uuid',
            'auth' => 'existing-hysteria-auth',
            'protocol' => 'hysteria',
        ]);
        $this->assertVlessConfigUsesInbound([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'name' => 'alice_ready_mixed_2',
            'uuid' => 'existing-hysteria-uuid',
            'auth' => 'existing-hysteria-auth',
            'protocol' => 'hysteria',
        ], 11);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/addClient'));
    }

    public function test_job_keeps_uuid_from_clients_list_uuid_field_instead_of_numeric_panel_id(): void
    {
        $server = $this->createVlessServer([
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
        ], [10]);

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

        $this->assertVlessConfigHas([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'name' => '123456789_ready_vless_1',
            'uuid' => '44444444-4444-4444-4444-444444444444',
        ]);
        $this->assertVlessConfigUsesInbound([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'name' => '123456789_ready_vless_1',
            'uuid' => '44444444-4444-4444-4444-444444444444',
        ], 10);
    }

    public function test_job_continues_creating_other_inbounds_when_one_inbound_fails(): void
    {
        $server = $this->createVlessServer([
            'name' => 'Ready VLESS',
            'code' => 'RVL',
            'ip' => '10.0.0.2',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ], [10, 11, 12]);

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

                if ((int) $request['id'] === 11) {
                    return Http::response(['message' => 'boom'], 500);
                }

                return Http::response(['success' => true], 200);
            },
            'https://panel.test/panel/api/clients/list' => Http::response([
                'obj' => [[
                    'id' => 1218,
                    'uuid' => '11111111-1111-1111-1111-111111111111',
                    'email' => '123456789_ready_vless_1',
                    'enable' => true,
                    'inboundIds' => [10],
                ], [
                    'id' => 1219,
                    'uuid' => '22222222-2222-2222-2222-222222222222',
                    'email' => '123456789_ready_vless_2',
                    'enable' => true,
                    'inboundIds' => [12],
                ]],
            ]),
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertVlessConfigHas([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 10,
            'name' => '123456789_ready_vless_1',
        ]);

        $this->assertVlessConfigMissing([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 11,
        ]);

        $this->assertDatabaseCount('vless_configs', 2);
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
        $server = $this->createVlessServer([
            'name' => 'Ready VLESS',
            'code' => 'RVL',
            'ip' => '10.0.0.2',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ], [10, 11]);

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
            'https://panel.test/panel/api/clients/list' => Http::response([
                'obj' => [[
                    'id' => 'admin-private-client-uuid',
                    'email' => 'admin_private_vless_1',
                    'enable' => true,
                    'inboundIds' => [10],
                ]],
            ]),
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertVlessConfigHas([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'inbound_id' => 10,
            'type' => 'tcp',
            'security' => 'reality',
            'flow' => 'xtls-rprx-vision',
        ]);

        $this->assertVlessConfigHas([
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
        $server = $this->createVlessServer([
            'name' => 'Ready VLESS',
            'code' => 'RVL',
            'ip' => '10.0.0.2',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ], [10]);

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
            'https://panel.test/panel/api/clients/list' => Http::response([
                'obj' => [[
                    'id' => 'admin-private-client-uuid',
                    'email' => 'admin_private_vless_1',
                    'enable' => true,
                    'inboundIds' => [10],
                ]],
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
        $server = $this->createVlessServer([
            'name' => 'Ready Trojan',
            'code' => 'RTJ',
            'ip' => '10.0.0.20',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ], [12]);

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
            'https://panel.test/panel/api/clients/list' => Http::response([
                'obj' => [[
                    'id' => 'admin-private-client-uuid',
                    'email' => 'admin_private_vless_1',
                    'enable' => true,
                    'inboundIds' => [10],
                ]],
            ]),
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertVlessConfigHas([
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
        $server = $this->createVlessServer([
            'name' => 'Legacy VLESS',
            'code' => 'LVL',
            'ip' => '10.0.0.3',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ], [10]);

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
            'https://panel.test/panel/api/clients/list' => Http::response([
                'obj' => [[
                    'id' => 1218,
                    'uuid' => '11111111-1111-1111-1111-111111111111',
                    'email' => 'alice_legacy_1',
                    'enable' => true,
                    'inboundIds' => [10],
                ]],
            ]),
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertVlessConfigHas([
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
        $server = $this->createVlessServer([
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
        ], [10]);

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
            'https://panel.test/panel/api/clients/list' => Http::response([
                'obj' => [[
                    'id' => 'modern-client-uuid',
                    'uuid' => '11111111-1111-1111-1111-111111111111',
                    'email' => 'alice_modern_1',
                    'enable' => true,
                    'inboundIds' => [10],
                ]],
            ]),
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertVlessConfigHas([
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
        $server = $this->createVlessServer([
            'name' => 'Ready Hysteria',
            'code' => 'RHY',
            'ip' => '10.0.0.5',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ], [10]);

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

        $this->assertVlessConfigHas([
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

    public function test_job_does_not_create_vless_config_for_private_inbound_for_non_admin_user(): void
    {
        $server = Server::query()->create([
            'name' => 'Private VLESS',
            'code' => 'PVL',
            'ip' => '10.0.0.20',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ]);

        $server->xrayInbounds()->create([
            'external_id' => 10,
            'is_active' => true,
            'is_public' => false,
            'params' => [],
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
            'is_admin' => false,
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 10,
        ]);

        Http::fake();

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertDatabaseCount('vless_configs', 0);
        Http::assertNothingSent();
    }

    public function test_job_creates_vless_config_for_private_inbound_for_admin_user(): void
    {
        $server = Server::query()->create([
            'name' => 'Private VLESS',
            'code' => 'PVL',
            'ip' => '10.0.0.21',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ]);

        $server->xrayInbounds()->create([
            'external_id' => 10,
            'is_active' => true,
            'is_public' => false,
            'params' => [],
        ]);

        $user = User::query()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
            'join_at' => now()->toDateString(),
            'is_admin' => true,
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
            'https://panel.test/panel/api/clients/list' => Http::response([
                'obj' => [[
                    'id' => 'admin-private-client-uuid',
                    'email' => 'admin_private_vless_1',
                    'enable' => true,
                    'inboundIds' => [10],
                ]],
            ]),
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertVlessConfigHas([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'inbound_id' => 10,
            'enable' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int>  $inboundIds
     */
    private function createVlessServer(array $attributes, array $inboundIds): Server
    {
        $server = Server::query()->create($attributes);

        $server->xrayInbounds()->createMany(
            collect($inboundIds)
                ->map(fn (int $externalId) => [
                    'external_id' => $externalId,
                    'is_active' => true,
                    'is_public' => true,
                ])
                ->all(),
        );

        return $server;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertVlessConfigHas(array $attributes): void
    {
        $inboundId = isset($attributes['inbound_id']) ? (int) $attributes['inbound_id'] : null;
        unset($attributes['inbound_id']);

        if ($inboundId === null) {
            $this->assertDatabaseHas('vless_configs', $attributes);

            return;
        }

        $config = VlessConfig::query()
            ->where($attributes)
            ->with('xrayInbound:id,external_id')
            ->get()
            ->first(fn (VlessConfig $config) => $config->getResolvedInboundId() === $inboundId);

        $this->assertNotNull($config);
        $this->assertSame($inboundId, $config->getResolvedInboundId());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertVlessConfigUsesInbound(array $attributes, int $inboundId): void
    {
        $this->assertVlessConfigHas([
            ...$attributes,
            'inbound_id' => $inboundId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertVlessConfigMissing(array $attributes): void
    {
        $inboundId = isset($attributes['inbound_id']) ? (int) $attributes['inbound_id'] : null;
        unset($attributes['inbound_id']);

        if ($inboundId === null) {
            $this->assertDatabaseMissing('vless_configs', $attributes);

            return;
        }

        $exists = VlessConfig::query()
            ->where($attributes)
            ->with('xrayInbound:id,external_id')
            ->get()
            ->contains(fn (VlessConfig $config) => $config->getResolvedInboundId() === $inboundId);

        $this->assertFalse($exists);
    }
}
