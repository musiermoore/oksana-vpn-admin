<?php

namespace Tests\Feature;

use App\Models\Config;
use App\Models\CurrentPayment;
use App\Models\Server;
use App\Models\TelegramAppToken;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\VlessExternalSubscription;
use App\Models\VlessExternalSubscriptionConfig;
use App\Support\BotApiMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TelegramAppConnectionRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_load_wireguard_configs_via_telegram_app_routes(): void
    {
        [$user, $token] = $this->createAuthorizedActiveUser();
        $server = $this->createServer('WG');
        $config = Config::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'name' => 'ios-main',
            'description' => 'Primary config',
            'is_active' => true,
        ]);

        $this->withToken($token)
            ->getJson('/telegram-app/wireguard/configs')
            ->assertOk()
            ->assertExactJson([
                'configs' => [[
                    'id' => $config->id,
                    'name' => 'ios-main',
                    'download_url' => "/telegram-app/wireguard/configs/{$config->id}/download",
                    'qr_code_url' => "/telegram-app/wireguard/configs/{$config->id}/qr-code",
                    'send_file_to_bot_url' => "/telegram-app/wireguard/configs/{$config->id}/send-file",
                    'send_qr_to_bot_url' => "/telegram-app/wireguard/configs/{$config->id}/send-qr",
                ]],
            ]);
    }

    public function test_wireguard_and_vless_routes_return_debt_payload_for_user_without_active_access(): void
    {
        [, $token] = $this->createAuthorizedUser(balance: 0);

        $this->withToken($token)
            ->getJson('/telegram-app/wireguard/configs')
            ->assertForbidden()
            ->assertExactJson([
                'type' => 'debt',
                'message' => BotApiMessages::accessRequiresPayment(),
            ]);

        $this->withToken($token)
            ->getJson('/telegram-app/vless/link')
            ->assertForbidden()
            ->assertExactJson([
                'type' => 'debt',
                'message' => BotApiMessages::accessRequiresPayment(),
            ]);
    }

    public function test_authenticated_user_can_load_vless_links_via_telegram_app_routes(): void
    {
        [$user, $token] = $this->createAuthorizedActiveUser();

        config()->set('vless.domain', 'https://vpn.example');

        $response = $this->withToken($token)
            ->getJson('/telegram-app/vless/link');

        $response->assertOk();
        $payload = $response->json();

        $this->assertStringContainsString('/connect/deep-link/happ', (string) ($payload['happ_deep_link'] ?? ''));
        $this->assertStringContainsString('/connect/deep-link/v2raytun', (string) ($payload['v2raytun_deeplink'] ?? ''));
        $this->assertStringContainsString('/connect/deep-link/incy', (string) ($payload['incy_deeplink'] ?? ''));
        $this->assertStringContainsString('/connect?', (string) ($payload['link'] ?? ''));
    }

    public function test_authenticated_admin_can_load_vless_wl_links_and_raw_link_via_telegram_app_routes(): void
    {
        [$user, $token] = $this->createAuthorizedActiveUser(isAdmin: true);
        $this->createVisibleWhiteListSubscription(isReady: false);

        $response = $this->withToken($token)
            ->getJson('/telegram-app/vless-wl/link');

        $response->assertOk();
        $payload = $response->json();

        $this->assertTrue((bool) ($payload['show_raw_link'] ?? false));
        $this->assertStringContainsString('/connect-wl', (string) ($payload['raw_link'] ?? ''));
        $this->assertStringContainsString('/connect-wl/deep-link/happ', (string) ($payload['happ_deep_link'] ?? ''));
    }

    public function test_authenticated_regular_user_can_load_vless_wl_links_without_raw_link(): void
    {
        [$user, $token] = $this->createAuthorizedActiveUser();
        $this->createVisibleWhiteListSubscription(isReady: true);

        $response = $this->withToken($token)
            ->getJson('/telegram-app/vless-wl/link');

        $response->assertOk()
            ->assertJsonPath('show_raw_link', false)
            ->assertJsonPath('raw_link', null);
    }

    public function test_telegram_app_profile_marks_vless_wl_visibility_per_user_role(): void
    {
        [$admin, $adminToken] = $this->createAuthorizedActiveUser(isAdmin: true, telegramId: '111111111');
        [$user, $userToken] = $this->createAuthorizedActiveUser(telegramId: '222222222');

        $this->createVisibleWhiteListSubscription(isReady: false);

        $this->withToken($adminToken)
            ->getJson('/telegram-app/me')
            ->assertOk()
            ->assertJsonPath('user.has_vless_wl_configs', true);

        $this->withToken($userToken)
            ->getJson('/telegram-app/me')
            ->assertOk()
            ->assertJsonPath('user.has_vless_wl_configs', false);
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function createAuthorizedUser(float $balance, bool $isAdmin = false, string $telegramId = '987654321'): array
    {
        $user = User::query()->create([
            'name' => 'Telegram App User',
            'telegram' => '@telegram-app-user',
            'telegram_id' => $telegramId,
            'join_at' => '2026-05-01',
            'balance' => $balance,
            'is_active' => true,
            'is_admin' => $isAdmin,
        ]);

        $plainTextToken = hash('sha256', $telegramId.'-'.Str::uuid());

        TelegramAppToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainTextToken),
            'last_used_at' => now(),
        ]);

        return [$user, $plainTextToken];
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function createAuthorizedActiveUser(bool $isAdmin = false, string $telegramId = '987654321'): array
    {
        [$user, $token] = $this->createAuthorizedUser(balance: 500, isAdmin: $isAdmin, telegramId: $telegramId);

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

        return [$user, $token];
    }

    private function createVisibleWhiteListSubscription(bool $isReady): void
    {
        $subscription = VlessExternalSubscription::query()->create([
            'name' => 'WL',
            'type' => VlessExternalSubscription::TYPE_DIRECT,
            'source_url' => 'vless://uuid@wl.example.com:443?type=tcp&security=reality#wl',
            'is_active' => true,
            'is_ready' => $isReady,
        ]);

        VlessExternalSubscriptionConfig::query()->create([
            'vless_external_subscription_id' => $subscription->id,
            'config_key' => 'wl-1',
            'name' => 'WL config',
            'normalized_name' => 'wl config',
            'protocol' => 'vless',
            'url' => 'vless://uuid@wl.example.com:443?type=tcp&security=reality#wl',
            'sort_order' => 0,
        ]);
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
