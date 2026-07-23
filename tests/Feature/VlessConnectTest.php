<?php

namespace Tests\Feature;

use App\Models\Proxy;
use App\Models\Server;
use App\Models\User;
use App\Models\UserServerStat;
use App\Models\UserSubscription;
use App\Models\VlessConfig;
use App\Models\VlessExternalSubscription;
use App\Models\VlessExternalSubscriptionConfig;
use App\Models\XrayInbound;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VlessConnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_connect_rewrites_subscription_names_and_numbers_duplicate_servers(): void
    {
        Http::fake([
            'https://lv-1.example.com/sub/sub-lv-1' => Http::response(base64_encode(
                "vless://uuid-1@lv-1.example.com?type=tcp&security=reality#old-name-1\n"
            )),
            'https://lv-2.example.com/sub/sub-lv-2' => Http::response(
                "vless://uuid-2@lv-2.example.com?type=tcp&security=reality#old-name-2\n"
            ),
            'https://fi.example.com/sub/sub-fi' => Http::response(base64_encode(
                "vless://uuid-3@fi.example.com?type=tcp&security=reality#legacy-fi\n"
            )),
        ]);

        $user = $this->createActiveUser('Test User', '@tester', '123456');

        $latviaOne = $this->createServer('Латвия', 'LV1', 'lv-1.example.com');
        $latviaTwo = $this->createServer('Латвия', 'LV2', 'lv-2.example.com');
        $finland = $this->createServer('Финляндия', 'FI', 'fi.example.com');
        $tallinn = $this->createServer('Таллин', 'EE', 'ee.example.com');

        $this->createConfig($user->id, $latviaOne->id, 'uuid-1', 'sub-lv-1');
        $this->createConfig($user->id, $latviaTwo->id, 'uuid-2', 'sub-lv-2');
        $this->createConfig($user->id, $finland->id, 'uuid-3', 'sub-fi');
        $this->createConfig($user->id, $tallinn->id, 'uuid-4');

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('123456'),
            'i' => Crypt::encrypt((string) $user->id),
        ]));

        $response->assertOk();

        $decoded = base64_decode($response->getContent(), true);

        $this->assertNotFalse($decoded);
        $names = $this->extractNames($decoded);
        sort($names);

        $expectedNames = [
            'Латвия • VLESS • TCP #1',
            'Латвия • VLESS • TCP #2',
            'Таллин • VLESS • TCP',
            'Финляндия • VLESS • TCP',
        ];
        sort($expectedNames);

        $this->assertSame($expectedNames, $names);
    }

    public function test_connect_includes_wireguard_configs_in_uri_subscription(): void
    {
        $user = $this->createActiveUser('WireGuard User', '@wg-user', '223344');

        $server = Server::query()->create([
            'name' => 'Латвия WG',
            'code' => 'LWG',
            'ip' => '10.10.10.10',
            'is_active' => true,
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ]);

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 8,
            'name' => 'WG-8pf78qlqc6-wg',
            'description' => null,
            'is_active' => true,
            'enable' => true,
            'uuid' => 'wg-client-8',
            'port' => 20466,
            'protocol' => 'wireguard',
            'type' => 'wireguard',
            'encryption' => 'none',
            'security' => 'none',
            'extra' => 'wireguard://aGGq0lnDIL1MLZoKPriZkFp+4qME1WdApNPoxduT0Hs=@lv.oksana1984.ru:20466?address=10.0.0.2/32&mtu=1420&publickey=X6MviN4r5SUGwdlMpY7ahO39/w2NumpTOHfK0zA6Q2Q=',
        ]);

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('223344'),
            'i' => Crypt::encrypt((string) $user->id),
        ]));

        $response->assertOk();

        $decoded = base64_decode($response->getContent(), true);

        $this->assertNotFalse($decoded);
        $this->assertStringContainsString('wireguard://aGGq0lnDIL1MLZoKPriZkFp%2B4qME1WdApNPoxduT0Hs%3D@lv.oksana1984.ru:20466', $decoded);
        $this->assertStringContainsString('address=10.0.0.2%2F32', $decoded);
        $this->assertStringContainsString('publickey=X6MviN4r5SUGwdlMpY7ahO39%2Fw2NumpTOHfK0zA6Q2Q%3D', $decoded);
        $this->assertStringContainsString('#'.rawurlencode('Латвия WG • WIREGUARD • UDP'), $decoded);
    }

    public function test_connect_percent_encodes_wireguard_keys_in_uri_subscription(): void
    {
        $user = $this->createActiveUser('WireGuard Encoded User', '@wg-encoded-user', '223355');

        $server = Server::query()->create([
            'name' => 'Латвия WG',
            'code' => 'LWG',
            'ip' => '10.10.10.11',
            'is_active' => true,
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ]);

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 9,
            'name' => 'WG-encoded-config',
            'description' => null,
            'is_active' => true,
            'enable' => true,
            'uuid' => 'wg-client-9',
            'port' => 51822,
            'protocol' => 'wireguard',
            'type' => 'wireguard',
            'encryption' => 'none',
            'security' => 'none',
            'extra' => 'wireguard://aCBriJh7qvg6tKO8zEybIyICRc3JS6AuqWWdx68+nnk=@lv.oksana1984.ru:51822?address=10.0.0.3/32&mtu=1420&publickey=X6MviN4r5SUGwdlMpY7ahO39/w2NumpTOHfK0zA6Q2Q=',
        ]);

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('223355'),
            'i' => Crypt::encrypt((string) $user->id),
        ]));

        $response->assertOk();

        $decoded = base64_decode((string) $response->getContent(), true);

        $this->assertNotFalse($decoded);
        $this->assertStringContainsString('wireguard://aCBriJh7qvg6tKO8zEybIyICRc3JS6AuqWWdx68%2Bnnk%3D@lv.oksana1984.ru:51822', $decoded);
        $this->assertStringContainsString('address=10.0.0.3%2F32', $decoded);
        $this->assertStringContainsString('publickey=X6MviN4r5SUGwdlMpY7ahO39%2Fw2NumpTOHfK0zA6Q2Q%3D', $decoded);
        $this->assertStringNotContainsString('wireguard://aCBriJh7qvg6tKO8zEybIyICRc3JS6AuqWWdx68+nnk=@lv.oksana1984.ru:51822', $decoded);
    }

    public function test_connect_json_returns_xray_profile_array_with_hardcoded_dns_and_route_settings(): void
    {
        $user = $this->createActiveUser('JSON User', '@json-user', '112233');

        $server = $this->createServer('Нидерланды', 'NL1', 'nl.oksana1984.ru');

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 10,
            'name' => 'json-vless',
            'is_active' => true,
            'enable' => true,
            'uuid' => '0b73ed9e-b1a3-4c32-bf43-3f966a4ff693',
            'port' => 39091,
            'protocol' => 'vless',
            'type' => 'tcp',
            'encryption' => 'none',
            'security' => 'reality',
            'sni' => 'xn--d1acpjx3f.xn--p1ai',
            'pbk' => 'cfNCWLrv_0ulogWqUp3adeAOzd49EbRZoBsWGhJKVUI',
            'sid' => 'c9bde63c',
            'fp' => 'edge',
            'flow' => 'xtls-rprx-vision',
            'spx' => '/',
        ]);

        $response = $this->get(route('vless.connect-json', [
            'tg' => Crypt::encrypt('112233'),
            'i' => Crypt::encrypt((string) $user->id),
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $response->assertHeader('Profile-Title', 'Oksana VPN JSON');

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertIsArray($payload);
        $this->assertCount(1, $payload);
        $this->assertSame('Нидерланды • VLESS • TCP', data_get($payload, '0.remarks'));
        $this->assertSame('warning', data_get($payload, '0.log.loglevel'));
        $this->assertSame('UseIPv4', data_get($payload, '0.dns.queryStrategy'));
        $this->assertSame('8.8.8.8', data_get($payload, '0.dns.servers.0.address'));
        $this->assertSame(['domain:openai.com', 'domain:chatgpt.com', 'domain:codex.com', 'domain:oaistatic.com', 'domain:oaiusercontent.com'], data_get($payload, '0.dns.servers.0.domains'));
        $this->assertSame('AsIs', data_get($payload, '0.routing.domainStrategy'));
        $this->assertSame('direct', data_get($payload, '0.routing.rules.4.outboundTag'));
        $this->assertSame('socks', data_get($payload, '0.inbounds.0.tag'));
        $this->assertSame('http', data_get($payload, '0.inbounds.1.tag'));
        $this->assertSame('vless', data_get($payload, '0.outbounds.0.protocol'));
        $this->assertSame('proxy', data_get($payload, '0.outbounds.0.tag'));
        $this->assertSame('nl.oksana1984.ru', data_get($payload, '0.outbounds.0.settings.vnext.0.address'));
        $this->assertSame('xtls-rprx-vision', data_get($payload, '0.outbounds.0.settings.vnext.0.users.0.flow'));
        $this->assertSame('reality', data_get($payload, '0.outbounds.0.streamSettings.security'));
        $this->assertSame('freedom', data_get($payload, '0.outbounds.1.protocol'));
        $this->assertSame('blackhole', data_get($payload, '0.outbounds.2.protocol'));
    }

    public function test_connect_json_can_return_base64_encoded_profile_array(): void
    {
        $user = $this->createActiveUser('JSON Base64 User', '@json-base64-user', '445566');

        $server = $this->createServer('Латвия', 'LV1', 'lv.oksana1984.ru');

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 10,
            'name' => 'json-vless-base64',
            'is_active' => true,
            'enable' => true,
            'uuid' => '10cd4746-6752-4c3c-b360-1704af468393',
            'port' => 11517,
            'protocol' => 'vless',
            'type' => 'tcp',
            'encryption' => 'none',
            'security' => 'reality',
            'sni' => 'www.ya.ru',
            'pbk' => 'rv_7uV8oMgoMv9D0e-WZwSs3siVB93caGjL5A_6MP0U',
            'sid' => '81ad',
            'fp' => 'firefox',
            'flow' => 'xtls-rprx-vision',
            'spx' => '/',
        ]);

        $response = $this->get(route('vless.connect-json', [
            'tg' => Crypt::encrypt('445566'),
            'i' => Crypt::encrypt((string) $user->id),
            'base64' => 'true',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json; charset=UTF-8');

        $decoded = base64_decode((string) $response->getContent(), true);

        $this->assertNotFalse($decoded);

        $payload = json_decode($decoded, true);

        $this->assertIsArray($payload);
        $this->assertSame('Латвия • VLESS • TCP', data_get($payload, '0.remarks'));
        $this->assertSame('lv.oksana1984.ru', data_get($payload, '0.outbounds.0.settings.vnext.0.address'));
    }

    public function test_connect_does_not_include_configs_from_inactive_xray_inbound(): void
    {
        $user = $this->createActiveUser('Hidden Inbound User', '@hidden-inbound-user', '991122');

        $server = $this->createServer('Швеция', 'SE1', 'se.oksana1984.ru');

        $xrayInbound = XrayInbound::query()->create([
            'server_id' => $server->id,
            'external_id' => 10,
            'is_active' => false,
            'is_public' => true,
            'params' => [],
        ]);

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'xray_inbound_id' => $xrayInbound->id,
            'inbound_id' => 10,
            'user_id' => $user->id,
            'name' => 'inactive-vless',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'inactive-vless-uuid',
            'port' => 443,
            'protocol' => 'vless',
            'type' => 'tcp',
            'encryption' => 'none',
            'security' => 'reality',
            'sni' => 'example.com',
            'pbk' => 'public-key',
            'sid' => 'short-id',
            'fp' => 'chrome',
            'flow' => 'xtls-rprx-vision',
            'spx' => '/',
        ]);

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('991122'),
            'i' => Crypt::encrypt((string) $user->id),
        ]));

        $response->assertOk();

        $decoded = base64_decode((string) $response->getContent(), true);

        $this->assertNotFalse($decoded);
        $this->assertStringNotContainsString('inactive-vless-uuid', $decoded);
        $this->assertStringNotContainsString('Швеция • VLESS • TCP', $decoded);
    }

    public function test_deep_link_route_redirects_to_v2rayng_subscription_import(): void
    {
        Http::fake([
            'https://lv-1.example.com/sub/sub-lv-1' => Http::response(base64_encode(
                "vless://uuid-1@lv-1.example.com?type=tcp&security=reality#old-name-1\n"
            )),
        ]);

        $user = $this->createActiveUser('Test User', '@tester', '123456');

        $server = $this->createServer('Латвия', 'LV1', 'lv-1.example.com');
        $this->createConfig($user->id, $server->id, 'uuid-1', 'sub-lv-1');

        $response = $this->get(route('vless.deep-link', [
            'client' => 'v2rayng',
            'token' => Crypt::encrypt([
                'tg' => '123456',
                'i' => (string) $user->id,
            ]),
        ]));

        $response->assertRedirect();

        $location = $response->headers->get('Location');

        $this->assertNotNull($location);
        $this->assertStringStartsWith('v2rayng://install-sub?url=', $location);
    }

    public function test_deep_link_route_redirects_to_incy_subscription_import(): void
    {
        Http::fake([
            'https://lv-1.example.com/sub/sub-lv-1' => Http::response(base64_encode(
                "vless://uuid-1@lv-1.example.com?type=tcp&security=reality#old-name-1\n"
            )),
        ]);

        $user = $this->createActiveUser('Test User', '@tester', '123456');

        $server = $this->createServer('Латвия', 'LV1', 'lv-1.example.com');
        $this->createConfig($user->id, $server->id, 'uuid-1', 'sub-lv-1');

        $response = $this->get(route('vless.deep-link', [
            'client' => 'incy',
            'token' => Crypt::encrypt([
                'tg' => '123456',
                'i' => (string) $user->id,
            ]),
        ]));

        $response->assertRedirect();

        $location = $response->headers->get('Location');

        $this->assertNotNull($location);
        $this->assertStringStartsWith('incy://import/', $location);
    }

    public function test_connect_raw_requires_basic_auth_and_returns_debug_json(): void
    {
        config([
            'auth.basic_auth.login' => 'debug-user',
            'auth.basic_auth.password' => 'debug-pass',
        ]);

        $user = $this->createActiveUser('Debug User', '@debug', '654321');

        $server = $this->createServer('Латвия', 'LV1', 'lv-1.example.com');

        $config = VlessConfig::query()->create([
            'server_id' => $server->id,
            'inbound_id' => 12,
            'user_id' => $user->id,
            'name' => 'debug-vless',
            'description' => null,
            'is_active' => true,
            'enable' => true,
            'uuid' => 'debug-uuid',
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

        $query = [
            'tg' => Crypt::encrypt('654321'),
            'i' => Crypt::encrypt((string) $user->id),
        ];

        $this->get(route('vless.connect-raw', $query))
            ->assertUnauthorized();

        $response = $this->withServerVariables([
            'PHP_AUTH_USER' => 'debug-user',
            'PHP_AUTH_PW' => 'debug-pass',
        ])->get(route('vless.connect-raw', $query));

        $response->assertOk()
            ->assertExactJson([
                [
                    'url' => $config->getLink(),
                    'config' => [
                        'id' => $config->id,
                        'name' => 'Латвия • VLESS • TCP',
                        'domain' => 'lv-1.example.com',
                        'port' => 443,
                        'protocol' => 'vless',
                        'transport' => 'tcp',
                        'inbound_id' => 12,
                    ],
                    'server' => [
                        'id' => $server->id,
                        'name' => 'Латвия',
                    ],
                ],
            ]);
    }

    public function test_connect_wl_returns_named_external_subscription_content(): void
    {
        $user = $this->createActiveUser('WL User', '@wl-user', '777777');

        $subscription = VlessExternalSubscription::query()->create([
            'name' => 'White List',
            'type' => VlessExternalSubscription::TYPE_SUBSCRIPTION,
            'source_url' => 'https://example.com/sub',
            'filter_pattern' => 'германия',
            'is_active' => true,
            'is_ready' => true,
        ]);

        VlessExternalSubscriptionConfig::query()->create([
            'vless_external_subscription_id' => $subscription->id,
            'config_key' => 'wl-1',
            'name' => 'Германия #1',
            'normalized_name' => 'германия #1',
            'protocol' => 'vless',
            'url' => 'vless://uuid-1@de.example.com:443?type=tcp&security=reality#de-1',
            'sort_order' => 0,
        ]);

        VlessExternalSubscriptionConfig::query()->create([
            'vless_external_subscription_id' => $subscription->id,
            'config_key' => 'wl-2',
            'name' => 'Германия #2',
            'normalized_name' => 'германия #2',
            'protocol' => 'trojan',
            'url' => 'trojan://secret@de2.example.com:443?security=tls&type=tcp#de-2',
            'sort_order' => 1,
        ]);

        $response = $this->get(route('vless.connect-wl', [
            'tg' => Crypt::encrypt('777777'),
            'i' => Crypt::encrypt((string) $user->id),
        ]));

        $response->assertOk();

        $decoded = base64_decode($response->getContent(), true);

        $this->assertNotFalse($decoded);
        $this->assertStringContainsString('vless://uuid-1@de.example.com:443', $decoded);
        $this->assertStringContainsString('trojan://secret@de2.example.com:443', $decoded);
        $this->assertSame([
            'White List • TROJAN • TCP',
            'White List • VLESS • TCP',
        ], collect($this->extractNames($decoded))->sort()->values()->all());
    }

    public function test_connect_wl_raw_requires_basic_auth_and_hides_admin_only_configs_from_regular_users(): void
    {
        config([
            'auth.basic_auth.login' => 'debug-user',
            'auth.basic_auth.password' => 'debug-pass',
        ]);

        $user = $this->createActiveUser('WL Debug User', '@wl-debug', '888888');

        $subscription = VlessExternalSubscription::query()->create([
            'name' => 'Admin only WL',
            'type' => VlessExternalSubscription::TYPE_DIRECT,
            'source_url' => 'vless://uuid-admin@admin.example.com:443?type=tcp&security=reality#admin',
            'is_active' => true,
            'is_ready' => false,
        ]);

        VlessExternalSubscriptionConfig::query()->create([
            'vless_external_subscription_id' => $subscription->id,
            'config_key' => 'wl-admin',
            'name' => 'Admin only',
            'normalized_name' => 'admin only',
            'protocol' => 'vless',
            'url' => 'vless://uuid-admin@admin.example.com:443?type=tcp&security=reality#admin',
            'sort_order' => 0,
        ]);

        $query = [
            'tg' => Crypt::encrypt('888888'),
            'i' => Crypt::encrypt((string) $user->id),
        ];

        $this->get(route('vless.connect-wl-raw', $query))
            ->assertUnauthorized();

        $this->withServerVariables([
            'PHP_AUTH_USER' => 'debug-user',
            'PHP_AUTH_PW' => 'debug-pass',
        ])->get(route('vless.connect-wl-raw', $query))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_deep_link_route_redirects_hiddify_to_clash_subscription(): void
    {
        Http::fake([
            'https://lv-1.example.com/sub/sub-lv-1' => Http::response(base64_encode(
                "vless://uuid-1@lv-1.example.com?type=tcp&security=reality#old-name-1\n"
            )),
        ]);

        $user = $this->createActiveUser('Test User', '@tester', '123456');

        $server = $this->createServer('Латвия', 'LV1', 'lv-1.example.com');
        $this->createConfig($user->id, $server->id, 'uuid-1', 'sub-lv-1');

        $response = $this->get(route('vless.deep-link', [
            'client' => 'hiddify',
            'token' => Crypt::encrypt([
                'tg' => '123456',
                'i' => (string) $user->id,
            ]),
        ]));

        $response->assertRedirect();

        $location = $response->headers->get('Location');

        $this->assertNotNull($location);
        $this->assertStringStartsWith('hiddify://import/', $location);
        $this->assertStringContainsString('format=clash', $location);
    }

    public function test_deep_link_route_redirects_sing_box_to_sing_box_subscription(): void
    {
        Http::fake([
            'https://lv-1.example.com/sub/sub-lv-1' => Http::response(base64_encode(
                "vless://uuid-1@lv-1.example.com?type=tcp&security=reality#old-name-1\n"
            )),
        ]);

        $user = $this->createActiveUser('Test User', '@tester', '123456');

        $server = $this->createServer('Латвия', 'LV1', 'lv-1.example.com');
        $this->createConfig($user->id, $server->id, 'uuid-1', 'sub-lv-1');

        $response = $this->get(route('vless.deep-link', [
            'client' => 'sing-box',
            'token' => Crypt::encrypt([
                'tg' => '123456',
                'i' => (string) $user->id,
            ]),
        ]));

        $response->assertRedirect();

        $location = $response->headers->get('Location');

        $this->assertNotNull($location);
        $this->assertStringStartsWith('sing-box://import-remote-profile?url=', $location);
        $this->assertStringContainsString('format=sing-box', urldecode($location));
    }

    public function test_deep_link_route_redirects_to_happ_encrypted_link(): void
    {
        Http::fake([
            'https://lv-1.example.com/sub/sub-lv-1' => Http::response(base64_encode(
                "vless://uuid-1@lv-1.example.com?type=tcp&security=reality#old-name-1\n"
            )),
            'https://crypto.happ.su/api-v2.php' => Http::response('happ://crypt5/some-encrypted-value'),
        ]);

        $user = $this->createActiveUser('Test User', '@tester', '123456');

        $server = $this->createServer('Латвия', 'LV1', 'lv-1.example.com');
        $this->createConfig($user->id, $server->id, 'uuid-1', 'sub-lv-1');

        $response = $this->get(route('vless.deep-link', [
            'client' => 'happ',
            'token' => Crypt::encrypt([
                'tg' => '123456',
                'i' => (string) $user->id,
            ]),
        ]));

        $response->assertRedirect('happ://crypt5/some-encrypted-value');
    }

    public function test_static_ws_link_contains_ws_transport_parameters(): void
    {
        $server = $this->createServer('Латвия', 'LV1', 'lv-1.example.com');

        $config = VlessConfig::query()->create([
            'server_id' => $server->id,
            'inbound_id' => 10,
            'user_id' => null,
            'name' => 'ws-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'ws-uuid',
            'port' => 8443,
            'type' => 'ws',
            'encryption' => 'none',
            'security' => 'tls',
            'sni' => 'ws.example.com',
            'host' => 'cdn.example.com',
            'path' => '/socket',
        ]);

        $this->assertSame(
            'vless://ws-uuid@lv-1.example.com:8443?type=ws&encryption=none&security=tls&sni=ws.example.com&host=cdn.example.com&path=%2Fsocket#lv1-ws-config',
            $config->getStaticLink()
        );
    }

    public function test_static_trojan_link_uses_trojan_scheme_and_password(): void
    {
        $server = $this->createServer('Латвия', 'LV1', 'lv-1.example.com');

        $config = VlessConfig::query()->create([
            'server_id' => $server->id,
            'inbound_id' => 10,
            'user_id' => null,
            'name' => 'trojan-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'trojan-uuid',
            'password' => 'trojan-password',
            'port' => 443,
            'protocol' => 'trojan',
            'type' => 'tcp',
            'encryption' => 'none',
            'security' => 'tls',
            'sni' => 'trojan.example.com',
        ]);

        $this->assertSame(
            'trojan://trojan-password@lv-1.example.com:443?security=tls&type=tcp&sni=trojan.example.com#lv1-trojan-config',
            $config->getStaticLink()
        );
    }

    public function test_static_xhttp_link_contains_xhttp_transport_parameters(): void
    {
        $server = $this->createServer('Латвия', 'LV1', 'lv.oksana1984.ru');

        $config = VlessConfig::query()->create([
            'server_id' => $server->id,
            'inbound_id' => 10,
            'user_id' => null,
            'name' => 'xhttp-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => '45f18045-9a4c-4e39-bb58-7b08db2d73df',
            'port' => 56120,
            'protocol' => 'vless',
            'type' => 'xhttp',
            'encryption' => 'none',
            'security' => 'reality',
            'pbk' => 'VBPWniidw6S8vDHAfqSxRxLxLx6Jthfx4DqaHxd1kBM',
            'fp' => 'firefox',
            'sni' => 'www.ya.ru',
            'host' => '',
            'path' => '/search',
            'mode' => 'auto',
            'extra' => '{"mode":"auto","xPaddingBytes":"0"}',
            'x_padding_bytes' => '0',
            'sid' => '5d262be3a53b',
            'spx' => '/NUyFkRoBI7YOXhg',
        ]);

        $this->assertSame(
            'vless://45f18045-9a4c-4e39-bb58-7b08db2d73df@lv.oksana1984.ru:56120?type=xhttp&encryption=none&security=reality&pbk=VBPWniidw6S8vDHAfqSxRxLxLx6Jthfx4DqaHxd1kBM&fp=firefox&sni=www.ya.ru&sid=5d262be3a53b&spx=%2FNUyFkRoBI7YOXhg&host=&path=%2Fsearch&mode=auto&extra=%7B%22mode%22%3A%22auto%22%2C%22xPaddingBytes%22%3A%220%22%7D&x_padding_bytes=0#lv1-xhttp-config',
            $config->getStaticLink()
        );
    }

    public function test_connect_builds_xhttp_links_locally_without_fetching_remote_subscription(): void
    {
        Http::fake();

        $user = $this->createActiveUser('XHTTP User', '@tester', '123456');

        $server = $this->createServer('Латвия', 'LV1', 'lv.oksana1984.ru');

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 4,
            'name' => 'xhttp-user-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => '45f18045-9a4c-4e39-bb58-7b08db2d73df',
            'sub_id' => 'remote-sub-id-should-not-be-fetched',
            'port' => 56120,
            'protocol' => 'vless',
            'type' => 'xhttp',
            'encryption' => 'none',
            'security' => 'reality',
            'pbk' => 'VBPWniidw6S8vDHAfqSxRxLxLx6Jthfx4DqaHxd1kBM',
            'fp' => 'firefox',
            'sni' => 'www.ya.ru',
            'host' => '',
            'path' => '/search',
            'mode' => 'auto',
            'extra' => '{"mode":"auto","xPaddingBytes":"0"}',
            'x_padding_bytes' => '0',
            'sid' => '5d262be3a53b',
            'spx' => '/NUyFkRoBI7YOXhg',
        ]);

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('123456'),
            'i' => Crypt::encrypt((string) $user->id),
        ]));

        $response->assertOk();

        $decoded = base64_decode((string) $response->getContent(), true);

        $this->assertNotFalse($decoded);
        $this->assertStringContainsString('type=xhttp', $decoded);
        $this->assertStringContainsString('path=%2Fsearch', $decoded);
        $this->assertStringContainsString('mode=auto', $decoded);
        $this->assertStringContainsString('x_padding_bytes=0', $decoded);

        Http::assertNothingSent();
    }

    public function test_connect_returns_local_and_remote_links_sorted_by_server_and_config_id(): void
    {
        Http::fake([
            'https://fi.example.com/sub/sub-fi' => Http::response(base64_encode(
                "vless://uuid-fi@fi.example.com?type=tcp&security=reality#old-fi\n"
            )),
        ]);

        $user = $this->createActiveUser('Test User', '@tester', '123456');

        $finland = $this->createServer('Финляндия', 'FI', 'fi.example.com');
        $latvia = $this->createServer('Латвия', 'LV', 'lv.example.com');
        $estonia = $this->createServer('Эстония', 'EE', 'ee.example.com');

        $vlessFinland = VlessConfig::query()->create([
            'server_id' => $finland->id,
            'user_id' => $user->id,
            'inbound_id' => 10,
            'name' => 'vless-fi',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'uuid-fi',
            'sub_id' => 'sub-fi',
            'port' => 443,
            'type' => 'tcp',
            'encryption' => 'none',
            'security' => 'reality',
            'pbk' => 'public-key',
            'fp' => 'chrome',
            'sni' => 'example.com',
            'sid' => 'abcd',
            'spx' => '/',
        ]);

        $vlessLatvia = VlessConfig::query()->create([
            'server_id' => $latvia->id,
            'user_id' => $user->id,
            'inbound_id' => 10,
            'name' => 'vless-lv',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'uuid-lv',
            'port' => 443,
            'type' => 'tcp',
            'encryption' => 'none',
            'security' => 'reality',
            'pbk' => 'public-key',
            'fp' => 'chrome',
            'sni' => 'example.com',
            'sid' => 'abcd',
            'spx' => '/',
        ]);

        VlessConfig::query()->create([
            'server_id' => $estonia->id,
            'user_id' => $user->id,
            'inbound_id' => 10,
            'name' => 'vless-ee',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'uuid-ee',
            'port' => 443,
            'type' => 'tcp',
            'encryption' => 'none',
            'security' => 'reality',
            'pbk' => 'public-key',
            'fp' => 'chrome',
            'sni' => 'example.com',
            'sid' => 'abcd',
            'spx' => '/',
        ]);

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('123456'),
            'i' => Crypt::encrypt((string) $user->id),
        ]));

        $response->assertOk();

        $decoded = base64_decode($response->getContent(), true);

        $this->assertNotFalse($decoded);

        $lines = collect(preg_split('/\r\n|\r|\n/', trim($decoded)))
            ->filter()
            ->values()
            ->all();

        $this->assertCount(3, $lines);
        $names = $this->extractNames($decoded);
        sort($names);

        $expectedNames = [
            'Латвия • VLESS • TCP',
            'Финляндия • VLESS • TCP',
            'Эстония • VLESS • TCP',
        ];
        sort($expectedNames);

        $this->assertSame($expectedNames, $names);
        $this->assertCount(3, array_filter($lines, fn (string $line) => str_starts_with($line, 'vless://')));
        $this->assertCount(0, array_filter($lines, fn (string $line) => str_starts_with($line, 'ss://')));
    }

    public function test_connect_returns_direct_and_proxy_links_when_ready_proxy_is_linked_to_server(): void
    {
        $user = $this->createActiveUser('Proxy User', '@tester', '123456');

        $server = $this->createServer('Латвия', 'LV', 'lv.example.com');
        $this->createProxy($server, 'Ru Proxy', 'proxy.example.com', 8443, 10);

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 10,
            'name' => 'proxy-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'proxy-uuid',
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

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('123456'),
            'i' => Crypt::encrypt((string) $user->id),
        ]));

        $response->assertOk();

        $decoded = base64_decode((string) $response->getContent(), true);

        $this->assertNotFalse($decoded);
        $this->assertStringContainsString('vless://proxy-uuid@lv.example.com:443?', $decoded);
        $this->assertStringContainsString('vless://proxy-uuid@proxy.example.com:8443?', $decoded);

        $names = $this->extractNames($decoded);

        $this->assertSame([
            'Латвия • VLESS • TCP',
            'Латвия (Ru Proxy) • VLESS • TCP',
        ], $names);
    }

    public function test_connect_prefers_proxy_with_matching_inbound_id_over_generic_proxy(): void
    {
        $user = $this->createActiveUser('Proxy User', '@tester', '123456');

        $server = $this->createServer('Латвия', 'LV', 'lv.example.com');
        $this->createProxy($server, 'Generic Proxy', 'generic.example.com', 8443, null);
        $this->createProxy($server, 'Inbound Proxy', 'inbound.example.com', 9443, 10);

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 10,
            'name' => 'proxy-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'proxy-uuid',
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

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('123456'),
            'i' => Crypt::encrypt((string) $user->id),
        ]));

        $response->assertOk();

        $decoded = base64_decode((string) $response->getContent(), true);

        $this->assertNotFalse($decoded);
        $this->assertStringContainsString('vless://proxy-uuid@lv.example.com:443?', $decoded);
        $this->assertStringContainsString('vless://proxy-uuid@inbound.example.com:9443?', $decoded);
        $this->assertStringNotContainsString('vless://proxy-uuid@generic.example.com:8443?', $decoded);
    }

    public function test_connect_does_not_use_generic_proxy_for_vless_when_exact_inbound_proxy_is_missing(): void
    {
        $user = $this->createActiveUser('Proxy User', '@tester', '123456');

        $server = $this->createServer('Латвия', 'LV', 'lv.example.com');
        $this->createProxy($server, 'Generic Proxy', 'generic.example.com', 8443, null);

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 10,
            'name' => 'proxy-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'proxy-uuid',
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

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('123456'),
            'i' => Crypt::encrypt((string) $user->id),
        ]));

        $response->assertOk();

        $decoded = base64_decode((string) $response->getContent(), true);

        $this->assertNotFalse($decoded);
        $this->assertStringContainsString('vless://proxy-uuid@lv.example.com:443?', $decoded);
        $this->assertStringNotContainsString('vless://proxy-uuid@generic.example.com:8443?', $decoded);
    }

    public function test_connect_includes_not_ready_proxy_for_admin_user_only(): void
    {
        $admin = $this->createActiveUser('Admin User', '@admin', '111111', true);

        $regularUser = $this->createActiveUser('Regular User', '@tester', '222222');

        $server = $this->createServer('Латвия', 'LV', 'lv.example.com');
        $this->createProxy($server, 'Ru Proxy', 'proxy.example.com', 8443, 10, false);

        foreach ([$admin, $regularUser] as $user) {
            VlessConfig::query()->create([
                'server_id' => $server->id,
                'user_id' => $user->id,
                'inbound_id' => 10,
                'name' => 'proxy-config-'.$user->id,
                'is_active' => true,
                'enable' => true,
                'uuid' => 'proxy-uuid-'.$user->id,
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
        }

        $adminResponse = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('111111'),
            'i' => Crypt::encrypt((string) $admin->id),
        ]));

        $adminResponse->assertOk();

        $adminDecoded = base64_decode((string) $adminResponse->getContent(), true);

        $this->assertNotFalse($adminDecoded);
        $this->assertStringContainsString('vless://proxy-uuid-'.$admin->id.'@proxy.example.com:8443?', $adminDecoded);

        $regularResponse = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('222222'),
            'i' => Crypt::encrypt((string) $regularUser->id),
        ]));

        $regularResponse->assertOk();

        $regularDecoded = base64_decode((string) $regularResponse->getContent(), true);

        $this->assertNotFalse($regularDecoded);
        $this->assertStringNotContainsString('vless://proxy-uuid-'.$regularUser->id.'@proxy.example.com:8443?', $regularDecoded);
    }

    public function test_connect_hides_configs_from_inactive_servers(): void
    {
        $user = $this->createActiveUser('Inactive Server User', '@tester', '123456');

        $activeServer = $this->createServer('Латвия', 'LV', 'lv.example.com');
        $inactiveServer = $this->createServer('Финляндия', 'FI', 'fi.example.com');
        $inactiveServer->update(['is_active' => false]);

        VlessConfig::query()->create([
            'server_id' => $activeServer->id,
            'inbound_id' => 10,
            'user_id' => $user->id,
            'name' => 'active-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'active-uuid',
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

        VlessConfig::query()->create([
            'server_id' => $inactiveServer->id,
            'inbound_id' => 10,
            'user_id' => $user->id,
            'name' => 'inactive-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'inactive-uuid',
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

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('123456'),
            'i' => Crypt::encrypt((string) $user->id),
        ]));

        $response->assertOk();

        $decoded = base64_decode((string) $response->getContent(), true);

        $this->assertNotFalse($decoded);
        $this->assertSame(['Латвия • VLESS • TCP'], $this->extractNames($decoded));
        $this->assertStringNotContainsString('inactive-uuid', $decoded);
    }

    public function test_connect_keeps_server_id_priority_for_same_named_servers(): void
    {
        Http::fake([
            'https://lv-2.example.com/sub/sub-lv-2' => Http::response(base64_encode(
                "vless://uuid-2@lv-2.example.com?type=tcp&security=reality#old-name-2\n"
            )),
            'https://lv-1.example.com/sub/sub-lv-1' => Http::response(base64_encode(
                "vless://uuid-1@lv-1.example.com?type=tcp&security=reality#old-name-1\n"
            )),
        ]);

        $user = $this->createActiveUser('Sort User', '@tester', '123456');

        $firstServer = $this->createServer('Латвия', 'LV1', 'lv-1.example.com');
        $secondServer = $this->createServer('Латвия', 'LV2', 'lv-2.example.com');

        $this->createConfig($user->id, $secondServer->id, 'uuid-2', 'sub-lv-2');
        $this->createConfig($user->id, $firstServer->id, 'uuid-1', 'sub-lv-1');

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('123456'),
            'i' => Crypt::encrypt((string) $user->id),
        ]));

        $response->assertOk();

        $decoded = base64_decode((string) $response->getContent(), true);

        $this->assertNotFalse($decoded);

        $names = $this->extractNames($decoded);

        $this->assertSame('Латвия • VLESS • TCP #1', $names[0] ?? null);
        $this->assertSame('Латвия • VLESS • TCP #2', $names[1] ?? null);
    }

    public function test_connect_returns_static_trojan_links_for_local_trojan_configs(): void
    {
        $user = $this->createActiveUser('Test User', '@tester', '123456');

        $latvia = $this->createServer('Латвия', 'LV', 'lv.example.com');

        VlessConfig::query()->create([
            'server_id' => $latvia->id,
            'inbound_id' => 10,
            'user_id' => $user->id,
            'name' => 'trojan-lv',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'trojan-uuid',
            'password' => 'trojan-password',
            'port' => 443,
            'protocol' => 'trojan',
            'type' => 'tcp',
            'encryption' => 'none',
            'security' => 'tls',
            'sni' => 'trojan.example.com',
        ]);

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('123456'),
            'i' => Crypt::encrypt((string) $user->id),
        ]));

        $response->assertOk();

        $decoded = base64_decode($response->getContent(), true);

        $this->assertNotFalse($decoded);
        $this->assertStringContainsString(
            'trojan://trojan-password@lv.example.com:443?security=tls&type=tcp&sni=trojan.example.com#'.rawurlencode('Латвия • TROJAN • TCP'),
            $decoded
        );
    }

    public function test_connect_returns_full_hysteria2_links_for_local_hysteria_configs(): void
    {
        $user = $this->createActiveUser('Test User', '@tester', '123456');

        $latvia = $this->createServer('Латвия', 'LV', 'lv.oksana1984.ru');

        VlessConfig::query()->create([
            'server_id' => $latvia->id,
            'inbound_id' => 10,
            'user_id' => $user->id,
            'name' => 'hysteria-lv',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'hysteria-uuid',
            'auth' => 'xrp11ixkmlsebrwe',
            'port' => 59885,
            'protocol' => 'hysteria',
            'type' => 'udp',
            'encryption' => 'none',
            'security' => 'tls',
            'alpn' => 'h2,http/1.1,h3',
            'fp' => 'firefox',
            'sni' => 'lv.oksana1984.ru',
            'obfs' => 'salamander',
            'obfs_password' => 'rva44wfs935cbf5s',
        ]);

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('123456'),
            'i' => Crypt::encrypt((string) $user->id),
        ]));

        $response->assertOk();

        $decoded = base64_decode($response->getContent(), true);

        $this->assertNotFalse($decoded);
        $this->assertStringContainsString(
            'hysteria2://xrp11ixkmlsebrwe@lv.oksana1984.ru:59885?alpn=h2%2Chttp%2F1.1%2Ch3&fm=%7B%22udp%22%3A%5B%7B%22settings%22%3A%7B%22password%22%3A%22rva44wfs935cbf5s%22%7D%2C%22type%22%3A%22salamander%22%7D%5D%7D&fp=firefox&obfs=salamander&obfs-password=rva44wfs935cbf5s&security=tls&sni=lv.oksana1984.ru#',
            $decoded
        );
    }

    public function test_hysteria2_static_link_prefers_auth_over_password_for_secret(): void
    {
        $server = $this->createServer('Латвия', 'LV', 'lv.oksana1984.ru');

        $config = VlessConfig::query()->create([
            'server_id' => $server->id,
            'inbound_id' => 10,
            'user_id' => null,
            'name' => 'hysteria-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'hysteria-uuid',
            'password' => 'legacy-password',
            'auth' => 'preferred-auth',
            'port' => 59885,
            'protocol' => 'hysteria',
            'type' => 'udp',
            'encryption' => 'none',
            'security' => 'tls',
            'alpn' => 'h2,http/1.1,h3',
            'sni' => 'lv.oksana1984.ru',
        ]);

        $this->assertStringStartsWith(
            'hysteria2://preferred-auth@lv.oksana1984.ru:59885?',
            $config->getStaticLink()
        );
    }

    public function test_connect_returns_subscription_metadata_headers_from_local_database(): void
    {
        $user = $this->createActiveUser(
            'Premium Subscription',
            '@tester',
            '123456',
            false,
            [
                'max_devices' => 5,
                'traffic_limit_bytes' => 107374182400,
                'subscription_expires_at' => now()->addDays(7)->endOfDay(),
            ],
        );

        $server = $this->createServer('Латвия', 'LV', 'lv.example.com');

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'inbound_id' => 10,
            'user_id' => $user->id,
            'name' => 'config-metadata',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'uuid-meta',
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

        UserServerStat::query()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'upload_bytes' => 1073741824,
            'download_bytes' => 5368709120,
        ]);

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('123456'),
            'i' => Crypt::encrypt((string) $user->id),
        ]));

        $response->assertOk();
        $response->assertHeader('Profile-Update-Interval', '1');
        $response->assertHeader('Profile-Title', 'Oksana VPN');
        $response->assertHeader('X-Subscription-Devices-Limit', '5');
        $response->assertHeader('X-Subscription-Devices-Used', '0');

        $userinfo = $response->headers->get('Subscription-Userinfo');

        $this->assertNotNull($userinfo);
        $this->assertStringContainsString('upload=1073741824', $userinfo);
        $this->assertStringContainsString('download=5368709120', $userinfo);
        $this->assertStringContainsString('total=107374182400', $userinfo);
    }

    public function test_connect_prefers_local_static_link_over_remote_subscription_source(): void
    {
        Http::fake([
            'https://de.example.com/sub/sub-de' => Http::response(base64_encode(implode("\n", [
                'vless://uuid-xhttp@de.example.com:443?type=xhttp&security=reality&path=%2Fxhttp&mode=auto&host=cdn.example.com&pbk=pk&fp=chrome&sni=example.com&sid=abcd&spx=%2F#legacy-xhttp',
                'hy2://secret@de.example.com:8443?sni=hy.example.com#legacy-hy2',
            ]))),
        ]);

        $user = $this->createActiveUser('Test User', '@tester', '123456');

        $server = $this->createServer('Германия', 'DE', 'de.example.com');

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'inbound_id' => 10,
            'user_id' => $user->id,
            'name' => 'remote-sub',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'uuid-remote',
            'sub_id' => 'sub-de',
            'port' => 443,
            'protocol' => 'vless',
            'type' => 'tcp',
            'encryption' => 'none',
            'security' => 'reality',
        ]);

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('123456'),
            'i' => Crypt::encrypt((string) $user->id),
        ]));

        $response->assertOk();

        $decoded = base64_decode($response->getContent(), true);

        $this->assertNotFalse($decoded);
        $this->assertStringContainsString('type=tcp', $decoded);
        $this->assertStringNotContainsString('type=xhttp', $decoded);
        $this->assertStringNotContainsString('hy2://secret@de.example.com:8443?sni=hy.example.com#', $decoded);
    }

    public function test_connect_returns_clash_subscription_with_auto_and_manual_groups(): void
    {
        $user = $this->createActiveUser('Clash User', '@tester', '123456');

        $server = $this->createServer('Германия', 'DE', 'de.example.com');

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'inbound_id' => 10,
            'user_id' => $user->id,
            'name' => 'xhttp-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'uuid-xhttp',
            'port' => 443,
            'protocol' => 'vless',
            'type' => 'xhttp',
            'encryption' => 'none',
            'security' => 'reality',
            'pbk' => 'pk',
            'fp' => 'chrome',
            'sni' => 'example.com',
            'sid' => 'abcd',
            'spx' => '/',
            'host' => 'cdn.example.com',
            'path' => '/xhttp',
            'mode' => 'auto',
        ]);

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'inbound_id' => 11,
            'user_id' => $user->id,
            'name' => 'hy2-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'uuid-hy2',
            'auth' => 'secret',
            'port' => 8443,
            'protocol' => 'hysteria',
            'type' => 'udp',
            'encryption' => 'none',
            'security' => 'tls',
            'alpn' => 'h3',
            'sni' => 'hy.example.com',
        ]);

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('123456'),
            'i' => Crypt::encrypt((string) $user->id),
            'format' => 'clash',
        ]));

        $response->assertOk();
        $response->assertHeader('Profile-Title', 'Oksana VPN');

        $content = $response->getContent();

        $this->assertIsString($content);
        $this->assertStringContainsString("name: 'Auto'", $content);
        $this->assertStringContainsString("type: 'url-test'", $content);
        $this->assertStringContainsString("name: 'Manual'", $content);
        $this->assertStringContainsString("xhttp-opts:", $content);
        $this->assertStringContainsString("type: 'hysteria2'", $content);
        $this->assertStringContainsString("name: 'Германия • VLESS • XHTTP'", $content);
        $this->assertStringContainsString("name: 'Германия • HYSTERIA2 • QUIC'", $content);
    }

    public function test_connect_returns_sing_box_subscription_with_auto_and_manual_outbounds(): void
    {
        $user = $this->createActiveUser('Sing User', '@tester', '123456');

        $server = $this->createServer('Латвия', 'LV', 'lv.example.com');

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'inbound_id' => 10,
            'user_id' => $user->id,
            'name' => 'grpc-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'grpc-uuid',
            'port' => 443,
            'protocol' => 'vless',
            'type' => 'grpc',
            'encryption' => 'none',
            'security' => 'tls',
            'sni' => 'example.com',
            'service_name' => 'edge',
        ]);

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('123456'),
            'i' => Crypt::encrypt((string) $user->id),
            'format' => 'sing-box',
        ]));

        $response->assertOk();
        $response->assertHeader('Profile-Title', 'Oksana VPN');

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertIsArray($payload);
        $this->assertSame('urltest', data_get($payload, 'outbounds.1.type'));
        $this->assertSame('Auto', data_get($payload, 'outbounds.1.tag'));
        $this->assertSame('selector', data_get($payload, 'outbounds.2.type'));
        $this->assertSame('Manual', data_get($payload, 'outbounds.2.tag'));
        $this->assertSame('grpc', data_get($payload, 'outbounds.0.transport.type'));
        $this->assertSame('edge', data_get($payload, 'outbounds.0.transport.service_name'));
        $this->assertSame('Латвия • VLESS • GRPC', data_get($payload, 'outbounds.0.tag'));
        $this->assertSame('Manual', data_get($payload, 'route.final'));
    }

    public function test_connect_returns_sing_box_wireguard_subscription_with_decoded_keys_and_address(): void
    {
        $user = $this->createActiveUser('WireGuard JSON User', '@wg-json', '998877');

        $server = $this->createServer('Латвия WG', 'LVWG', 'lv.oksana1984.ru');

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'inbound_id' => 10,
            'user_id' => $user->id,
            'name' => 'wg-json-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'wg-json-uuid',
            'port' => 20466,
            'protocol' => 'wireguard',
            'type' => 'wireguard',
            'encryption' => 'none',
            'security' => 'none',
            'extra' => 'wireguard://aCBriJh7qvg6tKO8zEybIyICRc3JS6AuqWWdx68+nnk=@lv.oksana1984.ru:20466?address=10.0.0.3/32&mtu=1420&publickey=X6MviN4r5SUGwdlMpY7ahO39/w2NumpTOHfK0zA6Q2Q=&presharedkey=KjD72IbwK366I5oq/f46MqZwAehb8Y3eG8slMQUMhzk=',
        ]);

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('998877'),
            'i' => Crypt::encrypt((string) $user->id),
            'format' => 'sing-box',
        ]));

        $response->assertOk();

        $content = (string) $response->getContent();
        $payload = json_decode($content, true);

        $this->assertIsArray($payload);
        $this->assertSame('wireguard', data_get($payload, 'outbounds.0.type'));
        $this->assertSame('aCBriJh7qvg6tKO8zEybIyICRc3JS6AuqWWdx68+nnk=', data_get($payload, 'outbounds.0.private_key'));
        $this->assertSame('X6MviN4r5SUGwdlMpY7ahO39/w2NumpTOHfK0zA6Q2Q=', data_get($payload, 'outbounds.0.peer_public_key'));
        $this->assertSame('KjD72IbwK366I5oq/f46MqZwAehb8Y3eG8slMQUMhzk=', data_get($payload, 'outbounds.0.pre_shared_key'));
        $this->assertSame(['10.0.0.3/32'], data_get($payload, 'outbounds.0.local_address'));
        $this->assertSame('lv.oksana1984.ru', data_get($payload, 'outbounds.0.server'));
        $this->assertSame(20466, data_get($payload, 'outbounds.0.server_port'));

        $this->assertNotFalse(base64_decode((string) data_get($payload, 'outbounds.0.private_key'), true));
        $this->assertNotFalse(base64_decode((string) data_get($payload, 'outbounds.0.peer_public_key'), true));
        $this->assertNotFalse(base64_decode((string) data_get($payload, 'outbounds.0.pre_shared_key'), true));
        $this->assertStringNotContainsString('%2F', $content);
        $this->assertStringNotContainsString('%3D', $content);
        $this->assertStringNotContainsString('%2B', $content);
        $this->assertStringNotContainsString('10.0.0.3%2F32/32', $content);
    }

    public function test_connect_returns_clash_wireguard_subscription_with_decoded_keys_and_address(): void
    {
        $user = $this->createActiveUser('WireGuard Clash User', '@wg-clash', '556677');

        $server = $this->createServer('Латвия WG', 'LVWG', 'lv.oksana1984.ru');

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'inbound_id' => 10,
            'user_id' => $user->id,
            'name' => 'wg-clash-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'wg-clash-uuid',
            'port' => 20466,
            'protocol' => 'wireguard',
            'type' => 'wireguard',
            'encryption' => 'none',
            'security' => 'none',
            'extra' => 'wireguard://aCBriJh7qvg6tKO8zEybIyICRc3JS6AuqWWdx68+nnk=@lv.oksana1984.ru:20466?address=10.0.0.3/32&mtu=1420&publickey=X6MviN4r5SUGwdlMpY7ahO39/w2NumpTOHfK0zA6Q2Q=',
        ]);

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('556677'),
            'i' => Crypt::encrypt((string) $user->id),
            'format' => 'clash',
        ]));

        $response->assertOk();

        $content = (string) $response->getContent();

        $this->assertStringContainsString("type: 'wireguard'", $content);
        $this->assertStringContainsString("private-key: 'aCBriJh7qvg6tKO8zEybIyICRc3JS6AuqWWdx68+nnk='", $content);
        $this->assertStringContainsString("public-key: 'X6MviN4r5SUGwdlMpY7ahO39/w2NumpTOHfK0zA6Q2Q='", $content);
        $this->assertStringContainsString("ip: '10.0.0.3/32'", $content);
        $this->assertStringNotContainsString('%2F', $content);
        $this->assertStringNotContainsString('%3D', $content);
        $this->assertStringNotContainsString('%2B', $content);
        $this->assertStringNotContainsString('10.0.0.3%2F32/32', $content);
    }

    private function createServer(string $name, string $code, string $host): Server
    {
        return Server::query()->create([
            'name' => $name,
            'code' => $code,
            'ip' => '127.0.0.1',
            'link_host' => $host,
            'is_https' => true,
            'type' => Server::TYPE_VLESS,
        ]);
    }

    private function createConfig(int $userId, int $serverId, string $uuid, ?string $subId = null): void
    {
        VlessConfig::query()->create([
            'server_id' => $serverId,
            'user_id' => $userId,
            'inbound_id' => 10,
            'name' => 'config-'.$uuid,
            'is_active' => true,
            'enable' => true,
            'uuid' => $uuid,
            'sub_id' => $subId,
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
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createActiveUser(
        string $name,
        string $telegram,
        string $telegramId,
        bool $isAdmin = false,
        array $attributes = [],
    ): User {
        $user = User::query()->create([
            'name' => $name,
            'telegram' => $telegram,
            'telegram_id' => $telegramId,
            'is_admin' => $isAdmin,
            ...$attributes,
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => Carbon::now()->subDay()->toDateString(),
            'end_date' => Carbon::now()->addMonth()->toDateString(),
            'price' => 100,
        ]);

        return $user;
    }

    private function createProxy(Server $server, string $name, string $host, int $port, ?int $inboundId = null, bool $isReady = true): Proxy
    {
        $proxy = Proxy::query()->create([
            'name' => $name,
            'host' => $host,
            'port' => $port,
            'inbound_id' => $inboundId,
            'is_https' => true,
            'is_ready' => $isReady,
        ]);

        $proxy->servers()->attach($server);

        return $proxy;
    }

    /**
     * @return array<int, string>
     */
    private function extractNames(string $decodedPayload): array
    {
        return collect(preg_split('/\r\n|\r|\n/', trim($decodedPayload)))
            ->filter()
            ->map(function (string $link) {
                $fragment = parse_url($link, PHP_URL_FRAGMENT);

                return $fragment === null ? null : rawurldecode($fragment);
            })
            ->filter()
            ->values()
            ->all();
    }
}
