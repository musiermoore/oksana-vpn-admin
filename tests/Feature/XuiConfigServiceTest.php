<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\VlessConfig;
use App\Services\XuiConfigService;
use App\Services\XuiConfigServiceFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XuiConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_enable_client_falls_back_to_inbound_settings_when_traffic_endpoint_is_missing(): void
    {
        $server = Server::query()->create([
            'name' => 'Legacy Panel',
            'code' => 'LPN',
            'ip' => '10.0.0.5',
            'app_path' => '/opt/app',
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
            'name' => 'alice_legacy_1',
            'description' => null,
            'is_active' => true,
            'enable' => false,
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'sub_id' => 'sub-id-123',
            'password' => 'secret-password',
            'auth' => 'secret-auth',
            'port' => 443,
            'protocol' => 'vless',
            'type' => 'tcp',
            'encryption' => 'none',
            'security' => 'reality',
            'flow' => 'xtls-rprx-vision',
            'pbk' => 'public-key',
            'fp' => 'chrome',
            'sni' => 'example.com',
            'host' => null,
            'path' => null,
            'service_name' => null,
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
            'https://panel.test/panel/api/inbounds/getClientTraffics/alice_legacy_1' => Http::response([], 404),
            'https://panel.test/panel/inbound/getClientTraffics/alice_legacy_1' => Http::response([], 404),
            'https://panel.test/panel/api/inbounds/list' => Http::response([
                'obj' => [[
                    'id' => 10,
                    'protocol' => 'vless',
                    'port' => 443,
                    'settings' => json_encode([
                        'clients' => [[
                            'id' => $config->uuid,
                            'email' => $config->name,
                            'flow' => $config->flow,
                            'subId' => $config->sub_id,
                            'password' => $config->password,
                            'auth' => $config->auth,
                            'limitIp' => 0,
                            'totalGB' => 0,
                            'expiryTime' => 0,
                            'enable' => false,
                        ]],
                    ], JSON_UNESCAPED_SLASHES),
                    'streamSettings' => json_encode([
                        'network' => 'tcp',
                        'security' => 'reality',
                    ], JSON_UNESCAPED_SLASHES),
                ]],
            ]),
            'https://panel.test/panel/api/inbounds/updateClient/' . $config->uuid => Http::response([
                'success' => true,
            ]),
        ]);

        $payload = (new XuiConfigService($server))->enableClient($config->uuid);

        $this->assertSame(['success' => true], $payload);

        Http::assertSent(function (Request $request) use ($config) {
            if ($request->url() !== 'https://panel.test/panel/api/inbounds/updateClient/' . $config->uuid) {
                return false;
            }

            $settings = json_decode((string) $request['settings'], true);
            $client = $settings['clients'][0] ?? [];

            return (int) $request['id'] === 10
                && ($client['id'] ?? null) === $config->uuid
                && ($client['email'] ?? null) === $config->name
                && ($client['subId'] ?? null) === $config->sub_id
                && ($client['password'] ?? null) === $config->password
                && ($client['auth'] ?? null) === $config->auth
                && ($client['enable'] ?? null) === true;
        });
    }

    public function test_v3_enable_client_uses_inbound_settings_without_calling_traffic_endpoint(): void
    {
        $server = Server::query()->create([
            'name' => 'Modern Panel',
            'code' => 'MPN',
            'ip' => '10.0.0.6',
            'app_path' => '/opt/app',
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
            'name' => 'alice_modern_1',
            'description' => null,
            'is_active' => true,
            'enable' => false,
            'uuid' => '22222222-2222-2222-2222-222222222222',
            'sub_id' => 'sub-id-456',
            'password' => 'modern-password',
            'auth' => 'modern-auth',
            'port' => 443,
            'protocol' => 'vless',
            'type' => 'tcp',
            'encryption' => 'none',
            'security' => 'reality',
            'flow' => 'xtls-rprx-vision',
            'pbk' => 'public-key',
            'fp' => 'chrome',
            'sni' => 'example.com',
            'host' => null,
            'path' => null,
            'service_name' => null,
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
            'https://panel.test/panel/api/inbounds/list' => Http::response([
                'obj' => [[
                    'id' => 10,
                    'protocol' => 'vless',
                    'port' => 443,
                    'settings' => json_encode([
                        'clients' => [[
                            'id' => $config->uuid,
                            'email' => $config->name,
                            'flow' => $config->flow,
                            'subId' => $config->sub_id,
                            'password' => $config->password,
                            'auth' => $config->auth,
                            'enable' => false,
                        ]],
                    ], JSON_UNESCAPED_SLASHES),
                    'streamSettings' => json_encode([
                        'network' => 'tcp',
                        'security' => 'reality',
                    ], JSON_UNESCAPED_SLASHES),
                ]],
            ]),
            'https://panel.test/panel/api/clients/update/' . $config->name . '?inboundIds=10' => Http::response([
                'success' => true,
            ]),
            'https://panel.test/panel/api/inbounds/getClientTraffics/alice_modern_1' => Http::response([], 500),
            'https://panel.test/panel/inbound/getClientTraffics/alice_modern_1' => Http::response([], 500),
        ]);

        $payload = XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server)
            ->enableClient($config->uuid);

        $this->assertSame(['success' => true], $payload);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'getClientTraffics'));
        Http::assertSent(function (Request $request) use ($config) {
            if ($request->url() !== 'https://panel.test/panel/api/clients/update/' . $config->name . '?inboundIds=10') {
                return false;
            }

            return ($request['id'] ?? null) === $config->uuid
                && ($request['email'] ?? null) === $config->name
                && ($request['subId'] ?? null) === $config->sub_id
                && ($request['password'] ?? null) === $config->password
                && ($request['auth'] ?? null) === $config->auth
                && ($request['enable'] ?? null) === true;
        });
    }

    public function test_v3_get_client_traffics_uses_clients_traffic_endpoint(): void
    {
        $server = Server::query()->create([
            'name' => 'Modern Panel',
            'code' => 'MPN',
            'ip' => '10.0.0.6',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'panel_api_version' => Server::PANEL_API_V3_2_8,
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
            'allowed_inbound_ids' => [10],
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
            'https://panel.test/panel/api/clients/traffic/alice_modern_1' => Http::response([
                'success' => true,
                'obj' => [
                    'email' => 'alice_modern_1',
                    'up' => 100,
                    'down' => 200,
                ],
            ]),
            'https://panel.test/panel/api/inbounds/getClientTraffics/alice_modern_1' => Http::response([], 500),
            'https://panel.test/panel/inbound/getClientTraffics/alice_modern_1' => Http::response([], 500),
        ]);

        $payload = XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server)
            ->getClientTraffics('alice_modern_1');

        $this->assertSame([
            'success' => true,
            'obj' => [
                'email' => 'alice_modern_1',
                'up' => 100,
                'down' => 200,
            ],
        ], $payload);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://panel.test/panel/api/clients/traffic/alice_modern_1');
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'getClientTraffics'));
    }

    public function test_service_prefers_csrf_token_endpoint_before_html_fallback(): void
    {
        $server = Server::query()->create([
            'name' => 'Modern Panel',
            'code' => 'MPN',
            'ip' => '10.0.0.6',
            'app_path' => '/opt/app',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'panel_api_version' => Server::PANEL_API_V3_2_8,
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
            'allowed_inbound_ids' => [10],
        ]);

        Http::fake([
            'https://panel.test/csrf-token' => Http::response([
                'success' => true,
                'obj' => 'csrf-token-from-endpoint',
            ], 200, [
                'Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly',
            ]),
            'https://panel.test/login' => Http::response([], 200, [
                'Set-Cookie' => '3x-ui=test-session; Path=/; HttpOnly',
            ]),
            'https://panel.test/panel/api/clients/traffic/alice_modern_1' => Http::response([
                'success' => true,
                'obj' => [
                    'email' => 'alice_modern_1',
                    'up' => 100,
                    'down' => 200,
                ],
            ]),
            'https://panel.test/' => Http::response(
                '<meta name="csrf-token" content="csrf-token-from-html">',
                200,
                ['Set-Cookie' => '3x-ui=html-session; Path=/; HttpOnly']
            ),
        ]);

        XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server)
            ->getClientTraffics('alice_modern_1');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://panel.test/csrf-token');
        Http::assertNotSent(fn (Request $request) => $request->url() === 'https://panel.test/');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://panel.test/login'
            && $request->hasHeader('X-CSRF-Token', 'csrf-token-from-endpoint'));
    }
}
