<?php

namespace Tests\Feature;

use App\Models\Proxy;
use App\Models\Server;
use App\Models\ShadowsocksConfig;
use App\Models\User;
use App\Models\UserServerStat;
use App\Models\VlessConfig;
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

        $user = User::query()->create([
            'name' => 'Test User',
            'telegram' => '@tester',
            'telegram_id' => '123456',
        ]);

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

    public function test_deep_link_route_redirects_to_v2rayng_subscription_import(): void
    {
        Http::fake([
            'https://lv-1.example.com/sub/sub-lv-1' => Http::response(base64_encode(
                "vless://uuid-1@lv-1.example.com?type=tcp&security=reality#old-name-1\n"
            )),
        ]);

        $user = User::query()->create([
            'name' => 'Test User',
            'telegram' => '@tester',
            'telegram_id' => '123456',
        ]);

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

    public function test_deep_link_route_redirects_hiddify_to_clash_subscription(): void
    {
        Http::fake([
            'https://lv-1.example.com/sub/sub-lv-1' => Http::response(base64_encode(
                "vless://uuid-1@lv-1.example.com?type=tcp&security=reality#old-name-1\n"
            )),
        ]);

        $user = User::query()->create([
            'name' => 'Test User',
            'telegram' => '@tester',
            'telegram_id' => '123456',
        ]);

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

        $user = User::query()->create([
            'name' => 'Test User',
            'telegram' => '@tester',
            'telegram_id' => '123456',
        ]);

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

        $user = User::query()->create([
            'name' => 'Test User',
            'telegram' => '@tester',
            'telegram_id' => '123456',
        ]);

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

    public function test_connect_returns_mixed_vless_and_shadowsocks_links_sorted_by_type_server_and_config_id(): void
    {
        Http::fake([
            'https://fi.example.com/sub/sub-fi' => Http::response(base64_encode(
                "vless://uuid-fi@fi.example.com?type=tcp&security=reality#old-fi\nss://Y2hhY2hhMjAtaWV0Zi1wb2x5MTMwNTpzZWNyZXQtcmVtb3Rl@fi.example.com:8388#old-ss\n"
            )),
        ]);

        $user = User::query()->create([
            'name' => 'Test User',
            'telegram' => '@tester',
            'telegram_id' => '123456',
        ]);

        $finland = $this->createServer('Финляндия', 'FI', 'fi.example.com');
        $latvia = $this->createServer('Латвия', 'LV', 'lv.example.com');
        $estonia = $this->createServer('Эстония', 'EE', 'ee.example.com');

        $vlessFinland = VlessConfig::query()->create([
            'server_id' => $finland->id,
            'user_id' => $user->id,
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

        $ssFinland = ShadowsocksConfig::query()->create([
            'server_id' => $finland->id,
            'user_id' => $user->id,
            'name' => 'ss-fi',
            'is_active' => true,
            'enable' => true,
            'port' => 8388,
            'method' => 'chacha20-ietf-poly1305',
            'password' => 'secret-1',
        ]);

        $ssEstonia = ShadowsocksConfig::query()->create([
            'server_id' => $estonia->id,
            'user_id' => $user->id,
            'name' => 'ss-ee',
            'is_active' => true,
            'enable' => true,
            'port' => 8388,
            'method' => 'chacha20-ietf-poly1305',
            'password' => 'secret-2',
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

        $this->assertCount(5, $lines);
        $names = $this->extractNames($decoded);
        sort($names);

        $expectedNames = [
            'Латвия • VLESS • TCP',
            'Финляндия • SHADOWSOCKS • TCP #1',
            'Финляндия • SHADOWSOCKS • TCP #2',
            'Финляндия • VLESS • TCP',
            'Эстония • SHADOWSOCKS • TCP',
        ];
        sort($expectedNames);

        $this->assertSame($expectedNames, $names);
        $this->assertCount(2, array_filter($lines, fn (string $line) => str_starts_with($line, 'vless://')));
        $this->assertCount(3, array_filter($lines, fn (string $line) => str_starts_with($line, 'ss://')));
    }

    public function test_connect_returns_direct_and_proxy_links_when_ready_proxy_is_linked_to_server(): void
    {
        $user = User::query()->create([
            'name' => 'Proxy User',
            'telegram' => '@tester',
            'telegram_id' => '123456',
        ]);

        $server = $this->createServer('Латвия', 'LV', 'lv.example.com');
        $this->createProxy($server, 'Ru Proxy', 'proxy.example.com', 8443);

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
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

        $user = User::query()->create([
            'name' => 'Sort User',
            'telegram' => '@tester',
            'telegram_id' => '123456',
        ]);

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
        $user = User::query()->create([
            'name' => 'Test User',
            'telegram' => '@tester',
            'telegram_id' => '123456',
        ]);

        $latvia = $this->createServer('Латвия', 'LV', 'lv.example.com');

        VlessConfig::query()->create([
            'server_id' => $latvia->id,
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

    public function test_connect_returns_subscription_metadata_headers_from_local_database(): void
    {
        $user = User::query()->create([
            'name' => 'Premium Subscription',
            'telegram' => '@tester',
            'telegram_id' => '123456',
            'max_devices' => 5,
            'traffic_limit_bytes' => 107374182400,
            'subscription_expires_at' => now()->addDays(7)->endOfDay(),
        ]);

        $server = $this->createServer('Латвия', 'LV', 'lv.example.com');

        VlessConfig::query()->create([
            'server_id' => $server->id,
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
        $response->assertHeader('Profile-Update-Interval', '24');
        $response->assertHeader('X-Subscription-Devices-Limit', '5');
        $response->assertHeader('X-Subscription-Devices-Used', '0');
        $response->assertHeader('Content-Disposition', 'attachment; filename="Premium Subscription.txt"');

        $userinfo = $response->headers->get('Subscription-Userinfo');

        $this->assertNotNull($userinfo);
        $this->assertStringContainsString('upload=1073741824', $userinfo);
        $this->assertStringContainsString('download=5368709120', $userinfo);
        $this->assertStringContainsString('total=107374182400', $userinfo);
    }

    public function test_connect_preserves_remote_xhttp_and_hysteria_links_from_subscription_source(): void
    {
        Http::fake([
            'https://de.example.com/sub/sub-de' => Http::response(base64_encode(implode("\n", [
                'vless://uuid-xhttp@de.example.com:443?type=xhttp&security=reality&path=%2Fxhttp&mode=auto&host=cdn.example.com&pbk=pk&fp=chrome&sni=example.com&sid=abcd&spx=%2F#legacy-xhttp',
                'hy2://secret@de.example.com:8443?sni=hy.example.com#legacy-hy2',
            ]))),
        ]);

        $user = User::query()->create([
            'name' => 'Test User',
            'telegram' => '@tester',
            'telegram_id' => '123456',
        ]);

        $server = $this->createServer('Германия', 'DE', 'de.example.com');

        VlessConfig::query()->create([
            'server_id' => $server->id,
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
        $this->assertStringContainsString('type=xhttp', $decoded);
        $this->assertStringContainsString('mode=auto', $decoded);
        $this->assertStringContainsString('hy2://secret@de.example.com:8443?sni=hy.example.com#', $decoded);
    }

    public function test_connect_returns_clash_subscription_with_auto_and_manual_groups(): void
    {
        Http::fake([
            'https://de.example.com/sub/sub-de' => Http::response(base64_encode(implode("\n", [
                'vless://uuid-xhttp@de.example.com:443?type=xhttp&security=reality&path=%2Fxhttp&mode=auto&host=cdn.example.com&pbk=pk&fp=chrome&sni=example.com&sid=abcd&spx=%2F#legacy-xhttp',
                'hy2://secret@de.example.com:8443?sni=hy.example.com#legacy-hy2',
            ]))),
        ]);

        $user = User::query()->create([
            'name' => 'Clash User',
            'telegram' => '@tester',
            'telegram_id' => '123456',
        ]);

        $server = $this->createServer('Германия', 'DE', 'de.example.com');

        VlessConfig::query()->create([
            'server_id' => $server->id,
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
            'format' => 'clash',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename="Clash User.yaml"');

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
        $user = User::query()->create([
            'name' => 'Sing User',
            'telegram' => '@tester',
            'telegram_id' => '123456',
        ]);

        $server = $this->createServer('Латвия', 'LV', 'lv.example.com');

        VlessConfig::query()->create([
            'server_id' => $server->id,
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
        $response->assertHeader('Content-Disposition', 'attachment; filename="Sing User.json"');

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

    private function createProxy(Server $server, string $name, string $host, int $port): Proxy
    {
        $proxy = Proxy::query()->create([
            'name' => $name,
            'host' => $host,
            'port' => $port,
            'is_https' => true,
            'is_ready' => true,
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
