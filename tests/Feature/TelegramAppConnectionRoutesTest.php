<?php

namespace Tests\Feature;

use App\Models\Config;
use App\Models\CurrentPayment;
use App\Models\Server;
use App\Models\TelegramAppToken;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\BotApiMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertStringContainsString('/connect?', (string) ($payload['link'] ?? ''));
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function createAuthorizedUser(float $balance): array
    {
        $user = User::query()->create([
            'name' => 'Telegram App User',
            'telegram' => '@telegram-app-user',
            'telegram_id' => '987654321',
            'join_at' => '2026-05-01',
            'balance' => $balance,
            'is_active' => true,
        ]);

        $plainTextToken = str_repeat('c', 80);

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
    private function createAuthorizedActiveUser(): array
    {
        [$user, $token] = $this->createAuthorizedUser(balance: 500);

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
