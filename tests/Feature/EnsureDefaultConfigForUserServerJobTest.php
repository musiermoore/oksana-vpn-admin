<?php

namespace Tests\Feature;

use App\Jobs\EnsureDefaultConfigForUserServerJob;
use App\Models\Server;
use App\Models\User;
use App\Models\UserSubscription;
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
            'is_vless' => false,
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
            'is_vless' => true,
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
            'is_vless' => true,
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
            'is_vless' => true,
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
            'is_vless' => true,
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
}
