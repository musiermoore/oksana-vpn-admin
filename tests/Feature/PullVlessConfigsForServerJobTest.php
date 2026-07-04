<?php

namespace Tests\Feature;

use App\Jobs\PullVlessConfigsForServerJob;
use App\Models\Server;
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

        (new PullVlessConfigsForServerJob($server->id))->handle();

        $this->assertDatabaseHas('vless_configs', [
            'server_id' => $server->id,
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
}
