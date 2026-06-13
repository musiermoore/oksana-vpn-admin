<?php

namespace Tests\Feature;

use App\Models\Config;
use App\Models\CurrentPayment;
use App\Models\Server;
use App\Models\ShadowsocksConfig;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\BotApiMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Tests\TestCase;

class ApiUserRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_route_returns_not_found_for_unknown_telegram_id(): void
    {
        $this->getJson('/api/users/missing-user/balance')
            ->assertNotFound()
            ->assertExactJson([
                'message' => BotApiMessages::userNotFound(),
            ]);
    }

    public function test_subscription_packages_route_returns_not_found_for_unknown_telegram_id(): void
    {
        $this->getJson('/api/users/missing-user/subscription-packages')
            ->assertNotFound()
            ->assertExactJson([
                'message' => BotApiMessages::userNotFound(),
            ]);
    }

    public function test_subscription_packages_route_returns_all_supported_packages_with_prices_and_discounts(): void
    {
        $user = $this->createUser(balance: 200);

        CurrentPayment::query()->create([
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'amount' => 400,
        ]);

        $this->getJson("/api/users/{$user->telegram_id}/subscription-packages")
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'month' => 1,
                        'price' => 400.0,
                        'discount_percent' => 0,
                    ],
                    [
                        'month' => 3,
                        'price' => 1080.0,
                        'discount_percent' => 10,
                    ],
                    [
                        'month' => 6,
                        'price' => 1920.0,
                        'discount_percent' => 20,
                    ],
                    [
                        'month' => 12,
                        'price' => 3360.0,
                        'discount_percent' => 30,
                    ],
                ],
            ]);
    }

    public function test_configs_route_returns_access_error_for_user_without_active_access(): void
    {
        $user = $this->createUser(balance: 0);

        $this->getJson("/api/users/{$user->telegram_id}/wireguard/configs")
            ->assertForbidden()
            ->assertExactJson([
                'type' => 'debt',
                'message' => BotApiMessages::accessRequiresPayment(),
            ]);
    }

    public function test_wireguard_configs_route_returns_config_resource_payload(): void
    {
        $user = $this->createActiveUser(balance: 500);
        $server = $this->createServer(code: 'WG');
        $config = Config::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'name' => 'ios-main',
            'description' => 'Primary config',
            'is_active' => true,
        ]);

        $this->getJson("/api/users/{$user->telegram_id}/wireguard/configs")
            ->assertOk()
            ->assertExactJson([
                'configs' => [
                    [
                        'id' => $config->id,
                        'name' => 'ios-main',
                        'download_url' => "/api/users/{$user->telegram_id}/configs/wireguard/{$config->id}/download",
                        'qr_code_url' => "/api/users/{$user->telegram_id}/configs/wireguard/{$config->id}/qr-code",
                    ],
                ],
            ]);
    }

    public function test_configs_route_hides_server_configs_for_non_admin_user(): void
    {
        $user = $this->createActiveUser(balance: 500);
        $server = $this->createServer(code: 'HID', attributes: [
            'hide_configs_for_non_admins' => true,
        ]);

        $config = Config::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'name' => 'hidden-config',
            'description' => null,
            'is_active' => true,
        ]);

        $this->getJson("/api/users/{$user->telegram_id}/wireguard/configs")
            ->assertOk()
            ->assertExactJson([
                'configs' => [],
            ]);

        $this->get("/api/users/{$user->telegram_id}/configs/wireguard/{$config->id}/download")
            ->assertNotFound()
            ->assertExactJson([
                'message' => BotApiMessages::configNotFound(),
            ]);
    }

    public function test_wireguard_download_route_builds_temporary_file_for_modern_wireguard_server(): void
    {
        $user = $this->createActiveUser(balance: 500);
        $server = Server::query()->create([
            'name' => 'Modern WG',
            'code' => 'MWG',
            'ip' => '127.0.0.1',
            'is_ready' => true,
            'type' => Server::TYPE_WIREGUARD,
            'panel_link' => 'https://agent.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
        ]);
        $config = Config::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'name' => 'ios-modern',
            'description' => null,
            'is_active' => true,
        ]);

        Http::fake([
            'https://agent.test/clients/*/config' => Http::response('[Interface]'."\n".'Address = 10.10.0.2/32'),
        ]);

        $response = $this->get("/api/users/{$user->telegram_id}/configs/wireguard/{$config->id}/download");

        $response->assertOk();
        $response->assertDownload('ios-modern.conf');
        $this->assertSame(
            "[Interface]\nAddress = 10.10.0.2/32\n",
            file_get_contents($response->baseResponse->getFile()->getPathname()),
        );
    }

    public function test_wireguard_qr_code_route_uses_agent_config_content_for_modern_wireguard_server(): void
    {
        $user = $this->createActiveUser(balance: 500);
        $server = Server::query()->create([
            'name' => 'Modern WG',
            'code' => 'MWG',
            'ip' => '127.0.0.1',
            'is_ready' => true,
            'type' => Server::TYPE_WIREGUARD,
            'panel_link' => 'https://agent.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
        ]);
        $config = Config::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'name' => 'ios-modern',
            'description' => null,
            'is_active' => true,
        ]);

        Http::fake([
            'https://agent.test/clients/*/config' => Http::response('[Interface]'."\n".'Address = 10.10.0.2/32'),
        ]);

        QrCode::swap(new class
        {
            public function format(string $format): self
            {
                return $this;
            }

            public function margin(int $margin): self
            {
                return $this;
            }

            public function size(int $size): self
            {
                return $this;
            }

            public function generate(string $content): string
            {
                return 'png-binary:' . $content;
            }
        });

        $response = $this->get("/api/users/{$user->telegram_id}/configs/wireguard/{$config->id}/qr-code");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertSame('png-binary:[Interface]' . "\n" . 'Address = 10.10.0.2/32', $response->getContent());
        Http::assertSent(function ($request) use ($config) {
            return $request->url() === 'https://agent.test/clients/' . rawurlencode($config->name) . '/config'
                && $request->method() === 'GET';
        });
    }

    public function test_vless_link_route_returns_connect_url_for_active_user(): void
    {
        config(['vless.domain' => 'https://vpn.example']);

        $user = $this->createActiveUser(balance: 500);

        $response = $this->getJson("/api/users/{$user->telegram_id}/vless/link");

        $response->assertOk();

        $payload = $response->json();
        $url = $payload['link'];

        $this->assertStringStartsWith('https://vpn.example/connect?', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $telegramId = Crypt::decrypt($query['tg']);
        $userId = Crypt::decrypt($query['i']);

        $this->assertSame($user->telegram_id, $telegramId);
        $this->assertSame((string) $user->id, (string) $userId);

        $this->assertArrayHasKey('happ_deep_link', $payload);
        $this->assertArrayHasKey('v2rayn_deeplink', $payload);
        $this->assertArrayHasKey('v2rayng_deeplink', $payload);
        $this->assertArrayHasKey('v2raybox_deeplink', $payload);
        $this->assertArrayHasKey('sing_box_deeplink', $payload);
        $this->assertArrayHasKey('hiddify_deeplink', $payload);
        $this->assertArrayHasKey('v2raytun_deeplink', $payload);

        $this->assertStringStartsWith('https://vpn.example/connect/deep-link/happ?', $payload['happ_deep_link']);
        $this->assertStringStartsWith('https://vpn.example/connect/deep-link/v2rayn?', $payload['v2rayn_deeplink']);

        parse_str((string) parse_url($payload['happ_deep_link'], PHP_URL_QUERY), $deepLinkQuery);
        $deepLinkCredentials = Crypt::decrypt($deepLinkQuery['token']);

        $this->assertSame($user->telegram_id, $deepLinkCredentials['tg']);
        $this->assertSame((string) $user->id, (string) $deepLinkCredentials['i']);
    }

    public function test_shadowsocks_configs_route_returns_config_resource_payload(): void
    {
        $user = $this->createActiveUser(balance: 500);
        $server = $this->createServer(code: 'SS');
        $config = ShadowsocksConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'name' => 'android-ss',
            'description' => 'Primary Shadowsocks config',
            'is_active' => true,
            'enable' => true,
            'port' => 8388,
            'method' => 'chacha20-ietf-poly1305',
            'password' => 'secret',
        ]);

        $this->getJson("/api/users/{$user->telegram_id}/shadowsocks/configs")
            ->assertOk()
            ->assertExactJson([
                'configs' => [
                    [
                        'id' => $config->id,
                        'name' => 'android-ss',
                        'download_url' => "/api/users/{$user->telegram_id}/configs/shadowsocks/{$config->id}/download",
                        'qr_code_url' => "/api/users/{$user->telegram_id}/configs/shadowsocks/{$config->id}/qr-code",
                    ],
                ],
            ]);
    }

    public function test_shadowsocks_download_route_returns_ss_link_for_active_user(): void
    {
        $user = $this->createActiveUser(balance: 500);
        $server = $this->createServer(code: 'SS');
        $config = ShadowsocksConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'name' => 'android-ss',
            'description' => null,
            'is_active' => true,
            'enable' => true,
            'port' => 8388,
            'method' => 'chacha20-ietf-poly1305',
            'password' => 'secret',
        ]);

        $response = $this->get("/api/users/{$user->telegram_id}/configs/shadowsocks/{$config->id}/download");

        $response->assertOk();
        $this->assertStringStartsWith('ss://', $response->getContent());
    }

    private function createUser(float $balance): User
    {
        return User::query()->create([
            'name' => 'API User',
            'telegram' => '@api-user',
            'telegram_id' => '123456789',
            'join_at' => '2026-05-01',
            'balance' => $balance,
            'is_active' => true,
        ]);
    }

    private function createActiveUser(float $balance): User
    {
        $user = $this->createUser($balance);

        $payment = CurrentPayment::query()->create([
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'amount' => 100,
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => $payment->start_date,
            'end_date' => $payment->end_date,
            'price' => 100,
        ]);

        return $user;
    }

    private function createServer(string $code, array $attributes = []): Server
    {
        return Server::query()->create([
            'name' => 'Server '.$code,
            'code' => $code,
            'ip' => '127.0.0.1',
            'is_ready' => true,
            'type' => Server::TYPE_WIREGUARD_OLD,
            'is_https' => true,
            'link_host' => strtolower($code).'.example.com',
        ] + $attributes);
    }
}
