<?php

namespace Tests\Feature;

use App\Jobs\PullVlessConfigsForServerJob;
use App\Models\Server;
use App\Models\User;
use App\Models\VlessConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PullVlessConfigsForServerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_merges_client_list_data_with_inbound_transport_metadata(): void
    {
        $server = Server::query()->create([
            'name' => 'Latvia',
            'code' => 'LV',
            'ip' => '10.0.0.6',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_active' => true,
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ]);
        $server->xrayInbounds()->create([
            'external_id' => 3,
            'is_active' => true,
            'is_public' => true,
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
                    'id' => 3,
                    'protocol' => 'hysteria',
                    'port' => 59885,
                    'settings' => [
                        'clients' => [[
                            'id' => 'd666060e-1b37-4aa7-908a-7728b913181d',
                            'email' => 'musiermoore_latviia_329',
                            'enable' => true,
                        ]],
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
            'https://panel.test/panel/api/clients/list' => Http::response([
                'obj' => [[
                    'email' => 'musiermoore_latviia_329',
                    'subId' => 'fdlznawhvuqlcq1r',
                    'uuid' => 'd666060e-1b37-4aa7-908a-7728b913181d',
                    'password' => 'tylydnqytfr0txtx',
                    'auth' => 'xrp11ixkmlsebrwe',
                    'flow' => '',
                    'enable' => true,
                    'inboundIds' => [3],
                ]],
            ]),
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 3,
            'name' => 'musiermoore_latviia_329',
            'uuid' => 'd666060e-1b37-4aa7-908a-7728b913181d',
            'protocol' => 'hysteria',
            'type' => 'hysteria',
            'is_active' => true,
            'enable' => true,
            'port' => 59885,
        ]);

        (new PullVlessConfigsForServerJob($server->id))->handle();

        $this->assertDatabaseHas('vless_configs', [
            'server_id' => $server->id,
            'user_id' => $user->id,
            'uuid' => 'd666060e-1b37-4aa7-908a-7728b913181d',
            'sub_id' => 'fdlznawhvuqlcq1r',
            'password' => 'tylydnqytfr0txtx',
            'auth' => 'xrp11ixkmlsebrwe',
            'protocol' => 'hysteria',
            'type' => 'hysteria',
            'security' => 'tls',
            'alpn' => 'h2,http/1.1,h3',
            'fp' => 'firefox',
            'sni' => 'lv.oksana1984.ru',
            'obfs' => 'salamander',
            'obfs_password' => 'rva44wfs935cbf5s',
        ]);
    }

    public function test_job_pulls_wireguard_inbounds_into_subscription_ready_records(): void
    {
        $server = Server::query()->create([
            'name' => 'WireGuard Panel',
            'code' => 'WGP',
            'ip' => '10.0.0.7',
            'link_host' => 'lv.oksana1984.ru',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_active' => true,
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ]);
        $server->xrayInbounds()->create([
            'external_id' => 8,
            'is_active' => true,
            'is_public' => true,
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
                    'id' => 8,
                    'protocol' => 'wireguard',
                    'port' => 20466,
                    'settings' => [
                        'publicKey' => 'X6MviN4r5SUGwdlMpY7ahO39/w2NumpTOHfK0zA6Q2Q=',
                        'mtu' => 1420,
                        'clients' => [[
                            'email' => 'WG-8pf78qlqc6-wg',
                            'privateKey' => 'aGGq0lnDIL1MLZoKPriZkFp+4qME1WdApNPoxduT0Hs=',
                            'address' => '10.0.0.2/32',
                            'enable' => true,
                        ]],
                    ],
                    'streamSettings' => json_encode([], JSON_UNESCAPED_SLASHES),
                ]],
            ]),
            'https://panel.test/panel/api/clients/list' => Http::response([
                'obj' => [[
                    'email' => 'WG-8pf78qlqc6-wg',
                    'privateKey' => 'aGGq0lnDIL1MLZoKPriZkFp+4qME1WdApNPoxduT0Hs=',
                    'address' => '10.0.0.2/32',
                    'enable' => true,
                    'inboundIds' => [8],
                ]],
            ]),
        ]);

        $user = User::query()->create([
            'name' => 'Bob',
            'telegram' => '@bob',
            'join_at' => now()->toDateString(),
        ]);

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 8,
            'name' => 'WG-8pf78qlqc6-wg',
            'uuid' => 'WG-8pf78qlqc6-wg',
            'protocol' => 'wireguard',
            'type' => 'wireguard',
            'is_active' => true,
            'enable' => true,
            'port' => 20466,
        ]);

        (new PullVlessConfigsForServerJob($server->id))->handle();

        $config = VlessConfig::query()
            ->where('server_id', $server->id)
            ->where('name', 'WG-8pf78qlqc6-wg')
            ->with('xrayInbound')
            ->get()
            ->first(fn (VlessConfig $config) => $config->getResolvedInboundId() === 8);

        $this->assertNotNull($config);
        $this->assertSame('wireguard', $config->protocol);
        $this->assertSame('wireguard', $config->type);
        $this->assertNotNull($config->extra);
        $this->assertStringStartsWith('wireguard://aGGq0lnDIL1MLZoKPriZkFp%2B4qME1WdApNPoxduT0Hs%3D@lv.oksana1984.ru:20466', $config->extra);
        $this->assertStringContainsString('address=10.0.0.2%2F32', $config->extra);
        $this->assertStringContainsString('publickey=X6MviN4r5SUGwdlMpY7ahO39%2Fw2NumpTOHfK0zA6Q2Q%3D', $config->extra);
    }

    public function test_job_normalizes_encoded_wireguard_candidate_uri_from_panel(): void
    {
        $server = Server::query()->create([
            'name' => 'WireGuard Panel',
            'code' => 'WGP',
            'ip' => '10.0.0.7',
            'link_host' => 'lv.oksana1984.ru',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_active' => true,
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ]);
        $server->xrayInbounds()->create([
            'external_id' => 8,
            'is_active' => true,
            'is_public' => true,
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
                    'id' => 8,
                    'protocol' => 'wireguard',
                    'port' => 20466,
                    'settings' => [
                        'clients' => [[
                            'email' => 'WG-8pf78qlqc6-wg',
                            'privateKey' => 'aGGq0lnDIL1MLZoKPriZkFp+4qME1WdApNPoxduT0Hs=',
                            'address' => '10.0.0.2/32',
                            'link' => 'wireguard://aGGq0lnDIL1MLZoKPriZkFp+4qME1WdApNPoxduT0Hs=@lv.oksana1984.ru:20466?address=10.0.0.2%252F32&mtu=1420&publickey=X6MviN4r5SUGwdlMpY7ahO39%252Fw2NumpTOHfK0zA6Q2Q%253D#old-name',
                            'enable' => true,
                        ]],
                    ],
                    'streamSettings' => json_encode([], JSON_UNESCAPED_SLASHES),
                ]],
            ]),
            'https://panel.test/panel/api/clients/list' => Http::response([
                'obj' => [[
                    'email' => 'WG-8pf78qlqc6-wg',
                    'privateKey' => 'aGGq0lnDIL1MLZoKPriZkFp+4qME1WdApNPoxduT0Hs=',
                    'address' => '10.0.0.2/32',
                    'link' => 'wireguard://aGGq0lnDIL1MLZoKPriZkFp+4qME1WdApNPoxduT0Hs=@lv.oksana1984.ru:20466?address=10.0.0.2%252F32&mtu=1420&publickey=X6MviN4r5SUGwdlMpY7ahO39%252Fw2NumpTOHfK0zA6Q2Q%253D#old-name',
                    'enable' => true,
                    'inboundIds' => [8],
                ]],
            ]),
        ]);

        $user = User::query()->create([
            'name' => 'Charlie',
            'telegram' => '@charlie',
            'join_at' => now()->toDateString(),
        ]);

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 8,
            'name' => 'WG-8pf78qlqc6-wg',
            'uuid' => 'WG-8pf78qlqc6-wg',
            'protocol' => 'wireguard',
            'type' => 'wireguard',
            'is_active' => true,
            'enable' => true,
            'port' => 20466,
        ]);

        (new PullVlessConfigsForServerJob($server->id))->handle();

        $config = VlessConfig::query()
            ->where('server_id', $server->id)
            ->where('name', 'WG-8pf78qlqc6-wg')
            ->with('xrayInbound')
            ->get()
            ->first(fn (VlessConfig $config) => $config->getResolvedInboundId() === 8);

        $this->assertNotNull($config);
        $this->assertNotNull($config->extra);
        $this->assertStringContainsString('address=10.0.0.2%2F32', $config->extra);
        $this->assertStringContainsString('publickey=X6MviN4r5SUGwdlMpY7ahO39%2Fw2NumpTOHfK0zA6Q2Q%3D', $config->extra);
        $this->assertStringNotContainsString('%252F', $config->extra);
        $this->assertStringNotContainsString('%253D', $config->extra);
    }

    public function test_job_percent_encodes_wireguard_keys_when_building_subscription_uri(): void
    {
        $server = Server::query()->create([
            'name' => 'WireGuard Panel',
            'code' => 'WGP',
            'ip' => '10.0.0.7',
            'link_host' => 'lv.oksana1984.ru',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_active' => true,
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ]);
        $server->xrayInbounds()->create([
            'external_id' => 8,
            'is_active' => true,
            'is_public' => true,
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
                    'id' => 8,
                    'protocol' => 'wireguard',
                    'port' => 51822,
                    'settings' => [
                        'publicKey' => 'X6MviN4r5SUGwdlMpY7ahO39/w2NumpTOHfK0zA6Q2Q=',
                        'mtu' => 1420,
                        'clients' => [[
                            'email' => 'WG-encoded-wg',
                            'privateKey' => 'aCBriJh7qvg6tKO8zEybIyICRc3JS6AuqWWdx68+nnk=',
                            'address' => '10.0.0.3/32',
                            'enable' => true,
                        ]],
                    ],
                    'streamSettings' => json_encode([], JSON_UNESCAPED_SLASHES),
                ]],
            ]),
            'https://panel.test/panel/api/clients/list' => Http::response([
                'obj' => [[
                    'email' => 'WG-encoded-wg',
                    'privateKey' => 'aCBriJh7qvg6tKO8zEybIyICRc3JS6AuqWWdx68+nnk=',
                    'address' => '10.0.0.3/32',
                    'enable' => true,
                    'inboundIds' => [8],
                ]],
            ]),
        ]);

        $user = User::query()->create([
            'name' => 'Encoded User',
            'telegram' => '@encoded',
            'join_at' => now()->toDateString(),
        ]);

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 8,
            'name' => 'WG-encoded-wg',
            'uuid' => 'WG-encoded-wg',
            'protocol' => 'wireguard',
            'type' => 'wireguard',
            'is_active' => true,
            'enable' => true,
            'port' => 51822,
        ]);

        (new PullVlessConfigsForServerJob($server->id))->handle();

        $config = VlessConfig::query()
            ->where('server_id', $server->id)
            ->where('name', 'WG-encoded-wg')
            ->first();

        $this->assertNotNull($config);
        $this->assertNotNull($config->extra);
        $this->assertStringContainsString('wireguard://aCBriJh7qvg6tKO8zEybIyICRc3JS6AuqWWdx68%2Bnnk%3D@lv.oksana1984.ru:51822', $config->extra);
        $this->assertStringContainsString('address=10.0.0.3%2F32', $config->extra);
        $this->assertStringContainsString('publickey=X6MviN4r5SUGwdlMpY7ahO39%2Fw2NumpTOHfK0zA6Q2Q%3D', $config->extra);
    }

    public function test_job_skips_panel_configs_without_existing_owned_local_record(): void
    {
        $server = Server::query()->create([
            'name' => 'Latvia',
            'code' => 'LV',
            'ip' => '10.0.0.6',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_active' => true,
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ]);
        $server->xrayInbounds()->create([
            'external_id' => 3,
            'is_active' => true,
            'is_public' => true,
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
                    'id' => 3,
                    'protocol' => 'hysteria',
                    'port' => 59885,
                    'settings' => [
                        'clients' => [[
                            'id' => 'd666060e-1b37-4aa7-908a-7728b913181d',
                            'email' => 'musiermoore_latviia_329',
                            'enable' => true,
                        ]],
                    ],
                    'streamSettings' => json_encode([
                        'network' => 'hysteria',
                        'security' => 'tls',
                    ], JSON_UNESCAPED_SLASHES),
                ]],
            ]),
            'https://panel.test/panel/api/clients/list' => Http::response([
                'obj' => [[
                    'email' => 'musiermoore_latviia_329',
                    'subId' => 'fdlznawhvuqlcq1r',
                    'uuid' => 'd666060e-1b37-4aa7-908a-7728b913181d',
                    'enable' => true,
                    'inboundIds' => [3],
                ]],
            ]),
        ]);

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => null,
            'inbound_id' => 3,
            'name' => 'musiermoore_latviia_329',
            'uuid' => 'd666060e-1b37-4aa7-908a-7728b913181d',
            'protocol' => 'hysteria',
            'type' => 'hysteria',
            'is_active' => true,
            'enable' => false,
            'port' => 59885,
        ]);

        (new PullVlessConfigsForServerJob($server->id))->handle();

        $this->assertDatabaseHas('vless_configs', [
            'server_id' => $server->id,
            'user_id' => null,
            'uuid' => 'd666060e-1b37-4aa7-908a-7728b913181d',
            'enable' => false,
        ]);

        $this->assertDatabaseCount('vless_configs', 1);
    }

    public function test_job_does_not_delete_owned_local_configs_missing_from_panel_payload(): void
    {
        $server = Server::query()->create([
            'name' => 'Latvia',
            'code' => 'LV',
            'ip' => '10.0.0.6',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_active' => true,
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ]);
        $server->xrayInbounds()->createMany([
            ['external_id' => 3, 'is_active' => true, 'is_public' => true],
            ['external_id' => 8, 'is_active' => true, 'is_public' => true],
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        $missingConfig = VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 3,
            'name' => 'alice_latvia_1',
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'protocol' => 'vless',
            'type' => 'tcp',
            'is_active' => true,
            'enable' => true,
            'port' => 443,
        ]);

        $keptConfig = VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 8,
            'name' => 'alice_latvia_2',
            'uuid' => '22222222-2222-2222-2222-222222222222',
            'protocol' => 'wireguard',
            'type' => 'wireguard',
            'is_active' => true,
            'enable' => true,
            'port' => 20466,
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
                    'id' => 8,
                    'protocol' => 'wireguard',
                    'port' => 20466,
                    'settings' => [
                        'publicKey' => 'X6MviN4r5SUGwdlMpY7ahO39/w2NumpTOHfK0zA6Q2Q=',
                        'clients' => [[
                            'email' => 'alice_latvia_2',
                            'privateKey' => 'aGGq0lnDIL1MLZoKPriZkFp+4qME1WdApNPoxduT0Hs=',
                            'address' => '10.0.0.2/32',
                            'enable' => true,
                        ]],
                    ],
                    'streamSettings' => json_encode([], JSON_UNESCAPED_SLASHES),
                ]],
            ]),
            'https://panel.test/panel/api/clients/list' => Http::response([
                'obj' => [[
                    'email' => 'alice_latvia_2',
                    'privateKey' => 'aGGq0lnDIL1MLZoKPriZkFp+4qME1WdApNPoxduT0Hs=',
                    'address' => '10.0.0.2/32',
                    'enable' => true,
                    'inboundIds' => [8],
                ]],
            ]),
        ]);

        (new PullVlessConfigsForServerJob($server->id))->handle();

        $this->assertDatabaseHas('vless_configs', [
            'id' => $missingConfig->id,
            'user_id' => $user->id,
            'uuid' => '11111111-1111-1111-1111-111111111111',
        ]);

        $this->assertDatabaseHas('vless_configs', [
            'id' => $keptConfig->id,
            'user_id' => $user->id,
            'name' => 'alice_latvia_2',
        ]);

        $this->assertDatabaseCount('vless_configs', 2);
    }
}
