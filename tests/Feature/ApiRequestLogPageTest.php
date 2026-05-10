<?php

namespace Tests\Feature;

use App\Models\ApiRequestLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApiRequestLogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_page_accepts_viewer_timezone_and_keeps_request_timezone_stats(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
        ]);

        $trackedUser = User::query()->create([
            'telegram' => '@client',
            'name' => 'Client',
        ]);

        ApiRequestLog::query()->create([
            'user_id' => $trackedUser->id,
            'action' => 'api.users.balance',
            'method' => 'GET',
            'endpoint' => 'api/users/{telegramId}/balance',
            'params' => ['query' => ['source' => 'telegram']],
            'request_timezone' => 'Europe/Berlin',
            'response_status' => 200,
        ]);

        ApiRequestLog::query()->create([
            'user_id' => $trackedUser->id,
            'action' => 'api.users.configs',
            'method' => 'GET',
            'endpoint' => 'api/users/{telegramId}/{type}/configs',
            'params' => ['route' => ['type' => 'wireguard']],
            'request_timezone' => 'Asia/Omsk',
            'response_status' => 200,
        ]);

        $this->actingAs($admin)
            ->get('/api-request-logs?viewer_timezone=Europe%2FBerlin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ApiRequestLogs/Index')
                ->where('overview.total', 2)
                ->where('viewer_timezone', 'Europe/Berlin')
                ->where('timezone_stats.0.timezone', 'Europe/Berlin')
                ->where('logs.data.0.action', 'api.users.balance')
                ->where('logs.data.0.user.telegram', '@client')
            );
    }

    public function test_logs_page_filters_datetimes_in_viewer_timezone(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
        ]);

        $trackedUser = User::query()->create([
            'telegram' => '@client',
            'name' => 'Client',
        ]);

        $included = ApiRequestLog::query()->create([
            'user_id' => $trackedUser->id,
            'action' => 'api.users.balance',
            'method' => 'GET',
            'endpoint' => 'api/users/{telegramId}/balance',
            'response_status' => 200,
        ]);

        $excluded = ApiRequestLog::query()->create([
            'user_id' => $trackedUser->id,
            'action' => 'api.users.configs',
            'method' => 'GET',
            'endpoint' => 'api/users/{telegramId}/{type}/configs',
            'response_status' => 200,
        ]);

        $included->forceFill([
            'created_at' => CarbonImmutable::parse('2026-05-10 00:30:00', 'UTC'),
            'updated_at' => CarbonImmutable::parse('2026-05-10 00:30:00', 'UTC'),
        ])->save();

        $excluded->forceFill([
            'created_at' => CarbonImmutable::parse('2026-05-09 17:30:00', 'UTC'),
            'updated_at' => CarbonImmutable::parse('2026-05-09 17:30:00', 'UTC'),
        ])->save();

        $this->actingAs($admin)
            ->get('/api-request-logs?viewer_timezone=Asia%2FOmsk&datetime_from=2026-05-10T06%3A00&datetime_to=2026-05-10T08%3A00')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ApiRequestLogs/Index')
                ->where('overview.total', 1)
                ->where('logs.data.0.action', 'api.users.balance')
            );
    }
}
