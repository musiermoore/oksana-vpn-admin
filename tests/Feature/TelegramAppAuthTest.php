<?php

namespace Tests\Feature;

use App\Models\TelegramAppToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramAppAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_telegram_app_auth_endpoint_creates_user_and_returns_token(): void
    {
        config()->set('services.telegram.bot_token', 'test-bot-token');

        $response = $this->postJson('/telegram-app/auth/telegram', [
            'init_data' => $this->buildInitData([
                'auth_date' => (string) now()->timestamp,
                'query_id' => 'AAHdF6IQAAAAAN0XohDhrOrc',
                'user' => json_encode([
                    'id' => 123456789,
                    'first_name' => 'Alice',
                    'last_name' => 'Doe',
                    'username' => 'alice',
                ], JSON_THROW_ON_ERROR),
            ]),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.telegram', '@alice')
            ->assertJsonPath('user.telegram_id', '123456789');

        $this->assertDatabaseHas('users', [
            'telegram' => '@alice',
            'telegram_id' => '123456789',
            'name' => 'Alice Doe',
        ]);

        $this->assertDatabaseCount('telegram_app_tokens', 1);
    }

    public function test_telegram_app_auth_creates_referral_relation_from_start_param_for_new_user(): void
    {
        config()->set('services.telegram.bot_token', 'test-bot-token');

        $referrer = User::query()->create([
            'name' => 'Referrer',
            'telegram' => '@referrer',
            'telegram_id' => '555',
        ]);

        $response = $this->postJson('/telegram-app/auth/telegram', [
            'init_data' => $this->buildInitData([
                'auth_date' => (string) now()->timestamp,
                'query_id' => 'AAHdF6IQAAAAAN0XohDhrOrc',
                'start_param' => 'ref_'.$referrer->id,
                'user' => json_encode([
                    'id' => 777888999,
                    'first_name' => 'Bob',
                    'username' => 'bob',
                ], JSON_THROW_ON_ERROR),
            ]),
        ]);

        $response->assertOk()
            ->assertJsonPath('user.telegram', '@bob');

        $this->assertDatabaseHas('users', [
            'telegram_id' => '777888999',
            'referrer_id' => $referrer->id,
        ]);

        $this->assertDatabaseHas('referrals', [
            'referrer_id' => $referrer->id,
        ]);
    }

    public function test_authorized_user_can_load_profile_via_bearer_token(): void
    {
        $user = User::factory()->create([
            'name' => 'Alice Doe',
            'telegram' => '@alice',
            'telegram_id' => '123456789',
        ]);

        $plainTextToken = str_repeat('a', 80);

        TelegramAppToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainTextToken),
            'last_used_at' => now(),
        ]);

        $response = $this->withToken($plainTextToken)
            ->getJson('/telegram-app/me');

        $response
            ->assertOk()
            ->assertJsonPath('user.telegram_id', '123456789')
            ->assertJsonPath('user.telegram', '@alice');
    }

    /**
     * @param  array<string, string>  $data
     */
    private function buildInitData(array $data): string
    {
        ksort($data);

        $dataCheckString = collect($data)
            ->map(fn (string $value, string $key) => $key.'='.$value)
            ->implode("\n");

        $secretKey = hash_hmac('sha256', 'test-bot-token', 'WebAppData', true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        return http_build_query([
            ...$data,
            'hash' => $hash,
        ]);
    }
}
