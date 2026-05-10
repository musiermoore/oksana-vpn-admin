<?php

namespace Tests\Feature;

use App\Jobs\StoreApiRequestLogJob;
use App\Models\ApiRequestLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
        ]);

        $log = ApiRequestLog::query()->latest()->first();

        $this->assertSame($payload['params'], $log?->params);
    }
}
