<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
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
        $this->assertSame([
            'Латвия - 1',
            'Латвия - 2',
            'Финляндия',
            'Таллин',
        ], $this->extractNames($decoded));
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

    private function createServer(string $name, string $code, string $host): Server
    {
        return Server::query()->create([
            'name' => $name,
            'code' => $code,
            'ip' => '127.0.0.1',
            'link_host' => $host,
            'is_https' => true,
            'is_vless' => true,
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
