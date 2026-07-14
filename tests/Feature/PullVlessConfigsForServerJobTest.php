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
            'allowed_inbound_ids' => [3],
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
            'allowed_inbound_ids' => [8],
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

        $config = \App\Models\VlessConfig::query()
            ->where('server_id', $server->id)
            ->where('inbound_id', 8)
            ->where('name', 'WG-8pf78qlqc6-wg')
            ->first();

        $this->assertNotNull($config);
        $this->assertSame('wireguard', $config->protocol);
        $this->assertSame('wireguard', $config->type);
        $this->assertNotNull($config->extra);
        $this->assertStringStartsWith('wireguard://aGGq0lnDIL1MLZoKPriZkFp+4qME1WdApNPoxduT0Hs=@lv.oksana1984.ru:20466', $config->extra);
        $this->assertStringContainsString('address=10.0.0.2/32', $config->extra);
        $this->assertStringContainsString('publickey=X6MviN4r5SUGwdlMpY7ahO39/w2NumpTOHfK0zA6Q2Q=', $config->extra);
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
            'allowed_inbound_ids' => [8],
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

        $config = \App\Models\VlessConfig::query()
            ->where('server_id', $server->id)
            ->where('inbound_id', 8)
            ->where('name', 'WG-8pf78qlqc6-wg')
            ->first();

        $this->assertNotNull($config);
        $this->assertNotNull($config->extra);
        $this->assertStringContainsString('address=10.0.0.2/32', $config->extra);
        $this->assertStringContainsString('publickey=X6MviN4r5SUGwdlMpY7ahO39/w2NumpTOHfK0zA6Q2Q=', $config->extra);
        $this->assertStringNotContainsString('%252F', $config->extra);
        $this->assertStringNotContainsString('%253D', $config->extra);
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
            'allowed_inbound_ids' => [3],
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

        (new PullVlessConfigsForServerJob($server->id))->handle();

        $this->assertDatabaseCount('vless_configs', 0);
    }
}
