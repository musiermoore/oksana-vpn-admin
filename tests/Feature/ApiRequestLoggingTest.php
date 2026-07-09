<?php

namespace Tests\Feature;

use App\Jobs\StoreApiRequestLogJob;
use App\Models\ApiRequestLog;
use App\Models\User;
use App\Support\ApiRequestLogPayloadFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiRequestLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_request_dispatches_log_job_with_user_and_timezone_context(): void
    {
        Queue::fake();

        $user = User::query()->create([
            'name' => 'Tester',
            'telegram' => '@tester',
            'telegram_id' => '123456',
            'balance' => 42,
        ]);

        $response = $this
            ->withHeaders([
                'X-Timezone' => 'Europe/Berlin',
                'X-Timezone-Offset' => '-120',
            ])
            ->getJson('/api/users/123456/balance?source=telegram');

        $response->assertOk()
            ->assertJson([
                'balance' => 42,
                'debt' => 0,
            ]);

        Queue::assertPushed(StoreApiRequestLogJob::class, function (StoreApiRequestLogJob $job) use ($user) {
            return $job->payload['user_id'] === $user->id
                && $job->payload['action'] === 'api.users.balance'
                && $job->payload['method'] === 'GET'
                && $job->payload['endpoint'] === 'api/users/{telegramId}/balance'
                && $job->payload['request_timezone'] === 'Europe/Berlin'
                && $job->payload['request_timezone_offset'] === -120
                && $job->payload['response_status'] === 200
                && $job->payload['params'] === [
                    'route' => [
                        'telegramId' => '123456',
                    ],
                    'query' => [
                        'source' => 'telegram',
                    ],
                ];
        });
    }

    public function test_registration_status_request_dispatches_log_job(): void
    {
        Queue::fake();

        $user = User::query()->create([
            'name' => 'Tester',
            'telegram' => '@tester',
            'telegram_id' => '123456',
            'balance' => 42,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/users/123456/registration-status');

        $response->assertOk()
            ->assertJsonPath('registered', true);

        Queue::assertPushed(StoreApiRequestLogJob::class, function (StoreApiRequestLogJob $job) use ($user) {
            return $job->payload['user_id'] === $user->id
                && $job->payload['action'] === 'api.users.registration-status'
                && $job->payload['method'] === 'GET'
                && $job->payload['endpoint'] === 'api/users/{telegramId}/registration-status'
                && $job->payload['response_status'] === 200
                && $job->payload['params'] === [
                    'route' => [
                        'telegramId' => '123456',
                    ],
                ];
        });
    }

    public function test_log_job_persists_request_log(): void
    {
        $payload = [
            'user_id' => null,
            'action' => 'api.users.balance',
            'method' => 'GET',
            'endpoint' => 'api/users/{telegramId}/balance',
            'params' => [
                'route' => ['telegramId' => '123456'],
            ],
            'request_timezone' => 'Europe/Berlin',
            'request_timezone_offset' => -120,
            'response_status' => 200,
            'ip_address' => '127.0.0.1',
            'forwarded_for' => '203.0.113.10, 172.18.0.1',
            'user_agent' => 'TestAgent/1.0',
        ];

        (new StoreApiRequestLogJob($payload))->handle();

        $this->assertDatabaseHas('api_request_logs', [
            'action' => 'api.users.balance',
            'method' => 'GET',
            'endpoint' => 'api/users/{telegramId}/balance',
            'request_timezone' => 'Europe/Berlin',
            'request_timezone_offset' => -120,
            'response_status' => 200,
            'ip_address' => '127.0.0.1',
            'forwarded_for' => '203.0.113.10, 172.18.0.1',
            'user_agent' => 'TestAgent/1.0',
        ]);

        $log = ApiRequestLog::query()->latest()->first();

        $this->assertSame($payload['params'], $log?->params);
    }

    public function test_payload_factory_resolves_connect_user_and_proxy_headers(): void
    {
        $user = User::query()->create([
            'name' => 'Connect User',
            'telegram' => '@connect-user',
            'telegram_id' => '123456',
        ]);

        $request = HttpRequest::create(route('vless.connect', [
            'tg' => Crypt::encrypt('123456'),
            'i' => Crypt::encrypt((string) $user->id),
        ], absolute: false), 'GET', server: [
            'REMOTE_ADDR' => '172.18.0.1',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.25, 172.18.0.1',
            'HTTP_USER_AGENT' => 'ConnectClient/2.0',
        ]);

        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);

        $payload = app(ApiRequestLogPayloadFactory::class)->fromRequest($request, new Response());

        $this->assertSame($user->id, $payload['user_id']);
        $this->assertSame('vless.connect', $payload['action']);
        $this->assertSame('connect', $payload['endpoint']);
        $this->assertStringContainsString('198.51.100.25', (string) $payload['forwarded_for']);
        $this->assertSame('ConnectClient/2.0', $payload['user_agent']);
    }

    public function test_payload_factory_resolves_deep_link_user_from_token(): void
    {
        $user = User::query()->create([
            'name' => 'Deep Link User',
            'telegram' => '@deep-link-user',
            'telegram_id' => '777777',
        ]);

        $token = Crypt::encrypt([
            'tg' => '777777',
            'i' => (string) $user->id,
        ]);

        $request = HttpRequest::create(route('vless.deep-link', [
            'client' => 'v2rayng',
            'token' => $token,
        ], absolute: false), 'GET', server: [
            'REMOTE_ADDR' => '172.18.0.1',
            'HTTP_X_REAL_IP' => '203.0.113.17',
            'HTTP_USER_AGENT' => 'DeepLinkClient/1.0',
        ]);

        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);

        $payload = app(ApiRequestLogPayloadFactory::class)->fromRequest($request, new Response());

        $this->assertSame($user->id, $payload['user_id']);
        $this->assertSame('vless.deep-link', $payload['action']);
        $this->assertSame('connect/deep-link/{client}', $payload['endpoint']);
        $this->assertSame('v2rayng', $payload['params']['route']['client'] ?? null);
        $this->assertSame($token, $payload['params']['query']['token'] ?? null);
        $this->assertStringContainsString('203.0.113.17', (string) $payload['forwarded_for']);
        $this->assertSame('DeepLinkClient/1.0', $payload['user_agent']);
    }
}
