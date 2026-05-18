<?php

namespace Tests\Feature;

use App\Models\Config;
use App\Models\CurrentPayment;
use App\Models\Server;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\BotApiMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
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

    public function test_vless_link_route_returns_connect_url_for_active_user(): void
    {
        config(['vless.domain' => 'https://vpn.example']);

        $user = $this->createActiveUser(balance: 500);

        $response = $this->get("/api/users/{$user->telegram_id}/vless/link");

        $response->assertOk();

        $url = $response->getContent();

        $this->assertStringStartsWith('https://vpn.example/connect?', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame($user->telegram_id, Crypt::decrypt($query['tg']));
        $this->assertSame((string) $user->id, (string) Crypt::decrypt($query['i']));
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

    private function createServer(string $code): Server
    {
        return Server::query()->create([
            'name' => 'Server '.$code,
            'code' => $code,
            'ip' => '127.0.0.1',
            'is_ready' => true,
            'is_vless' => false,
            'is_https' => true,
            'link_host' => strtolower($code).'.example.com',
        ]);
    }
}
