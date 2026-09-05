<?php

namespace Tests\Feature;

use App\Models\TelegramAppToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_telegram_app_auth_allows_existing_user_to_attach_referrer_once_from_start_param(): void
    {
        config()->set('services.telegram.bot_token', 'test-bot-token');

        $referrer = User::query()->create([
            'name' => 'Referrer',
            'telegram' => '@referrer',
            'telegram_id' => '555',
        ]);

        $user = User::query()->create([
            'name' => 'Bob',
            'telegram' => '@bob',
            'telegram_id' => '777888999',
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
            ->assertJsonPath('user.telegram_id', '777888999');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
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

    public function test_telegram_app_login_page_is_available(): void
    {
        $this->get('/telegram-app/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('TelegramApp/Login')
                ->where('routes.home', route('telegram-app.home'))
                ->where('password_auth_url', route('telegram-app.auth.password'))
            );
    }

    public function test_telegram_app_register_page_is_available(): void
    {
        $this->get('/telegram-app/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('TelegramApp/Register')
                ->where('routes.login', route('telegram-app.login'))
                ->where('password_registration_url', route('telegram-app.auth.register'))
            );
    }

    public function test_telegram_app_password_auth_returns_mini_app_token(): void
    {
        User::factory()->create([
            'name' => 'Alice Doe',
            'login' => 'alice',
            'password' => Hash::make('secret-password'),
            'telegram' => '@alice',
            'telegram_id' => '123456789',
        ]);

        $response = $this->postJson('/telegram-app/auth/login', [
            'login' => 'alice',
            'password' => 'secret-password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.telegram', '@alice')
            ->assertJsonPath('user.telegram_id', '123456789')
            ->assertJsonStructure(['token', 'expires_at', 'user']);

        $this->assertDatabaseCount('telegram_app_tokens', 1);
    }

    public function test_telegram_app_password_auth_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'login' => 'alice',
            'password' => Hash::make('secret-password'),
        ]);

        $this->postJson('/telegram-app/auth/login', [
            'login' => 'alice',
            'password' => 'wrong-password',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Неверный логин или пароль.');

        $this->assertDatabaseCount('telegram_app_tokens', 0);
    }

    public function test_telegram_app_password_registration_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/telegram-app/auth/register', [
            'name' => 'Alice Doe',
            'login' => 'alice',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.name', 'Alice Doe')
            ->assertJsonPath('user.telegram_id', null)
            ->assertJsonStructure(['token', 'expires_at', 'user']);

        $this->assertDatabaseHas('users', [
            'name' => 'Alice Doe',
            'login' => 'alice',
            'is_admin' => false,
        ]);
        $this->assertTrue(Hash::check('secret-password', (string) User::query()->where('login', 'alice')->value('password')));
        $this->assertDatabaseCount('telegram_app_tokens', 1);
    }

    public function test_telegram_app_password_registration_requires_unique_login(): void
    {
        User::factory()->create([
            'login' => 'alice',
            'password' => Hash::make('secret-password'),
        ]);

        $this->postJson('/telegram-app/auth/register', [
            'name' => 'Alice Doe',
            'login' => 'alice',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['login']);

        $this->assertDatabaseCount('telegram_app_tokens', 0);
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
