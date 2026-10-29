<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDailyTraffic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanupDailyTrafficCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_keeps_ninety_days_of_daily_traffic_and_deletes_older_rows(): void
    {
        $user = User::query()->create([
            'name' => 'Traffic User',
            'telegram' => '@traffic-user',
            'join_at' => today()->toDateString(),
        ]);

        $oldTraffic = UserDailyTraffic::query()->create([
            'user_id' => $user->id,
            'traffic_date' => today()->subDays(90)->toDateString(),
            'upload_bytes' => 100,
            'download_bytes' => 200,
        ]);
        $retainedTraffic = UserDailyTraffic::query()->create([
            'user_id' => $user->id,
            'traffic_date' => today()->subDays(89)->toDateString(),
            'upload_bytes' => 300,
            'download_bytes' => 400,
        ]);

        $this->artisan('traffic:cleanup-daily')->assertSuccessful();

        $this->assertDatabaseMissing('user_daily_traffic', ['id' => $oldTraffic->id]);
        $this->assertDatabaseHas('user_daily_traffic', ['id' => $retainedTraffic->id]);
    }
}
