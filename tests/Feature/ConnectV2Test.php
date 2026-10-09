<?php

namespace Tests\Feature;

use App\Jobs\StoreApiRequestLogJob;
use App\DTOs\Subscription\SubscriptionBuildResult;
use App\Services\Subscriptions\UserSubscriptionService;
use App\Models\User;
use App\Models\VlessExternalSubscription;
use App\Models\VlessExternalSubscriptionConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ConnectV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_incy_user_agent_redirects_to_incy_deep_link(): void
    {
        $user = $this->createUser();

        $response = $this
            ->withHeader('User-Agent', 'INCY/2.4.5')
            ->get(route('vless.connect-v2', [
                'token' => $user->uuid,
                'format' => 'txt',
            ]));

        $this->assertIsArray($response->json());

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
            ->assertHeader('Profile-Title', 'Oksana VPN v2')
            ->assertHeader('Support-Url', 'https://t.me/OksanaVpnBot');
        $this->assertFalse($response->headers->has('Hide-Url'));
        $this->assertFalse($response->headers->has('Hide-Proxy'));
        $this->assertNoConfigHidingHeaders($response);
    }

    public function test_happ_receives_plain_json_without_special_headers(): void
    {
        $user = $this->createUser();

        $response = $this
            ->withHeader('User-Agent', 'Happ/1.0')
            ->get(route('vless.connect-v2', ['token' => $user->uuid]));

        $this->assertIsArray($response->json());

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8');

        $this->assertNoConfigHidingHeaders($response);
    }

    public function test_happ_deep_link_redirects_even_when_requested_by_happ_android(): void
    {
        Http::fake([
            'https://crypto.happ.su/api-v2.php' => Http::response('happ://crypt5/encrypted-value'),
        ]);

        $user = $this->createUser();

        $this
            ->withHeader('User-Agent', 'Happ/3.26.1 Android')
            ->get(route('vless.connect-v2-deep-link', [
                'client' => 'happ',
                'token' => $user->uuid,
            ]))
            ->assertRedirect('happ://crypt5/encrypted-value');

        Http::assertSent(static fn ($request): bool => str_contains(
            (string) $request->data()['url'],
            '/start?token='.$user->uuid.'&app=happ',
        ));
    }

    public function test_incy_deep_link_redirects_to_plain_encoded_add_link(): void
    {
        $user = $this->createUser();

        $this
            ->get(route('vless.connect-v2-deep-link', [
                'client' => 'incy',
                'token' => $user->uuid,
            ]))
            ->assertRedirect('incy://add/'.urlencode(
                'https://connect.oksana1984.ru/start?token='.$user->uuid.'&app=incy'
            ));
    }

    public function test_v2raytun_deep_link_redirects_to_plain_import_link(): void
    {
        $user = $this->createUser();

        $this
            ->get(route('vless.connect-v2-deep-link', [
                'client' => 'v2raytun',
                'token' => $user->uuid,
            ]))
            ->assertRedirect(
                'v2raytun://import/https://connect.oksana1984.ru/start?token='
                .$user->uuid.'&app=v2raytun'
            );
    }

    public function test_app_parameter_allows_happ_subscription_without_happ_user_agent(): void
    {
        $user = $this->createUser();

        $response = $this
            ->withHeader('User-Agent', 'Mozilla/5.0')
            ->get(route('vless.connect-v2', [
                'token' => $user->uuid,
                'app' => 'happ',
            ]));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $this->assertNoConfigHidingHeaders($response);
    }

    public function test_happ_app_parameter_returns_one_diagnostic_profile(): void
    {
        $user = $this->createUser();

        $response = $this
            ->withHeader('User-Agent', 'Mozilla/5.0')
            ->getJson(route('vless.connect-v2', [
                'token' => $user->uuid,
                'app' => 'happ',
            ]));

        $response
            ->assertOk();

        $response
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
            ->assertJsonStructure();

        $this->assertFalse($response->headers->has('Hide-Settings'));
        $this->assertStringNotContainsString('Happ test', $response->getContent());
    }

    public function test_v2raytun_app_parameter_returns_standard_and_custom_profiles_with_xhttp_support(): void
    {
        $user = $this->createUser();

        $response = $this
            ->withHeader('User-Agent', 'Mozilla/5.0')
            ->getJson(route('vless.connect-v2', [
                'token' => $user->uuid,
                'app' => 'v2raytun',
            ]));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
            ->assertJsonStructure();
        $this->assertNoConfigHidingHeaders($response);
    }

    public function test_connect_v2_passes_app_parameter_to_subscription_builder(): void
    {
        $user = $this->createUser();
        $builder = Mockery::mock(UserSubscriptionService::class);
        $builder->shouldReceive('buildConnectV2')
            ->once()
            ->withArgs(static fn (User $resolvedUser, ?string $app): bool =>
                $resolvedUser->is($user) && $app === 'v2raytun'
            )
            ->andReturn(new SubscriptionBuildResult(
                content: '[]',
                contentType: 'application/json; charset=UTF-8',
                fileExtension: 'json',
            ));
        $this->app->instance(UserSubscriptionService::class, $builder);

        $this
            ->getJson(route('vless.connect-v2', [
                'token' => $user->uuid,
                'app' => 'v2raytun',
            ]))
            ->assertOk();
    }

    public function test_connect_v2_includes_only_external_subscriptions_enabled_for_connect_v2(): void
    {
        $user = $this->createUser();

        foreach ([
            ['name' => 'External subscription name', 'config_name' => 'Name in subscription', 'include_in_connect_v2' => true],
            ['name' => 'Hidden external subscription', 'config_name' => 'Hidden name in subscription', 'include_in_connect_v2' => false],
        ] as $index => $attributes) {
            $subscription = VlessExternalSubscription::query()->create([
                'name' => $attributes['name'],
                'sort_order' => $index,
                'type' => VlessExternalSubscription::TYPE_DIRECT,
                'connect_name_prefix' => $attributes['include_in_connect_v2'] ? 'Connect WL Name' : null,
                'source_url' => 'vless://external-'.$index.'@external-'.$index.'.example.com:443?type=tcp#External',
                'include_in_main_subscription' => false,
                'include_in_whitelist' => false,
                'include_in_connect_v2' => $attributes['include_in_connect_v2'],
                'is_free' => true,
                'is_active' => true,
                'is_ready' => true,
            ]);

            VlessExternalSubscriptionConfig::query()->create([
                'vless_external_subscription_id' => $subscription->id,
                'config_key' => 'external-'.$index,
                'name' => $attributes['config_name'],
                'normalized_name' => mb_strtolower($attributes['config_name']),
                'protocol' => 'vless',
                'url' => 'vless://external-'.$index.'@external-'.$index.'.example.com:443?type=tcp#External',
                'sort_order' => 0,
            ]);
        }

        $response = $this
            ->withHeader('User-Agent', 'INCY/2.4.5')
            ->get(route('vless.connect-v2', ['token' => $user->uuid]));

        $response->assertOk();
        $this->assertStringContainsString('external-0.example.com', $response->getContent());
        $this->assertStringContainsString('Connect WL Name', $response->getContent());
        $this->assertStringNotContainsString('External subscription name', $response->getContent());
        $this->assertStringNotContainsString('external-1.example.com', $response->getContent());
    }

    public function test_happ_deep_link_uses_android_intent_in_chrome(): void
    {
        Http::fake([
            'https://crypto.happ.su/api-v2.php' => Http::response('happ://crypt5/encrypted-value'),
        ]);

        $user = $this->createUser();

        $this
            ->withoutMiddleware(\App\Http\Middleware\TrackApiRequests::class)
            ->withHeader(
                'User-Agent',
                'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/130.0.0.0 Mobile Safari/537.36'
            )
            ->get(route('vless.connect-v2-deep-link', [
                'client' => 'happ',
                'token' => $user->uuid,
            ]))
            ->assertOk()
            ->assertSee('intent://crypt5/encrypted-value', false)
            ->assertSee('package=com.happproxy', false);
    }

    public function test_v2raytun_user_agent_receives_plain_json(): void
    {
        $user = $this->createUser();

        $response = $this
            ->withHeader('User-Agent', 'V2RayTun/6.0')
            ->get(route('vless.connect-v2', ['token' => $user->uuid]));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $this->assertNoConfigHidingHeaders($response);
    }

    public function test_postman_receives_plain_json_for_any_user(): void
    {
        $admin = $this->createUser(['is_admin' => true]);
        $regularUser = $this->createUser();

        $this
            ->withHeader('User-Agent', 'PostmanRuntime/7.0')
            ->get(route('vless.connect-v2', ['token' => $admin->uuid]))
            ->assertOk();

        $this
            ->withHeader('User-Agent', 'PostmanRuntime/7.0')
            ->get(route('vless.connect-v2', ['token' => $regularUser->uuid]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8');
    }

    public function test_unknown_user_agent_receives_plain_json(): void
    {
        $user = $this->createUser();

        $this
            ->withHeader('User-Agent', 'UnknownClient/1.0')
            ->get(route('vless.connect-v2', ['token' => $user->uuid]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8');
    }

    public function test_incy_subscription_includes_telegram_links_in_metadata_headers(): void
    {
        config()->set('services.telegram.incy_links', [
            'bot' => 'https://t.me/test_bot',
            'news' => 'https://t.me/test_news',
            'chat' => 'https://t.me/test_chat',
        ]);

        $user = $this->createUser();

        $response = $this
            ->withHeader('User-Agent', 'INCY/2.4.5')
            ->get(route('vless.connect', [
                'tg' => Crypt::encrypt($user->telegram_id),
                'i' => Crypt::encrypt((string) $user->id),
            ]));

        $response
            ->assertOk()
            ->assertHeader('Support-Url', 'https://t.me/test_bot')
            ->assertHeader('Profile-Web-Page-Url', 'https://t.me/test_news')
            ->assertHeader('Announce-Url', 'https://t.me/test_chat')
            ->assertHeader('Hide-Url', 'true')
            ->assertHeader('Hide-Proxy', 'true');
    }

    public function test_connect_v2_request_log_contains_user_and_query_parameters(): void
    {
        Queue::fake();
        $user = $this->createUser();

        $this
            ->withHeader('User-Agent', 'INCY/2.4.5')
            ->get(route('vless.connect-v2', [
                'token' => $user->uuid,
                'source' => 'qr',
            ]));

        Queue::assertPushed(StoreApiRequestLogJob::class, function (StoreApiRequestLogJob $job) use ($user): bool {
            return $job->payload['user_id'] === $user->id
                && $job->payload['action'] === 'vless.connect-v2'
                && $job->payload['params']['query']['token'] === $user->uuid
                && $job->payload['params']['query']['source'] === 'qr';
        });
    }

    private function assertNoConfigHidingHeaders($response): void
    {
        foreach ([
            'Hide-Url',
            'Hide-Proxy',
            'Hide-Settings',
        ] as $header) {
            $this->assertFalse($response->headers->has($header), "Unexpected {$header} header.");
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(array $attributes = []): User
    {
        return User::query()->create([
            'name' => 'Connect V2 User',
            'uuid' => fake()->uuid(),
            'telegram' => '@connect-v2-user',
            'telegram_id' => (string) fake()->unique()->numberBetween(100000, 999999),
            ...$attributes,
        ]);
    }
}
