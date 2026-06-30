<?php

namespace Tests\Feature;

use App\Models\ActiveConnection;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class ActiveConnectionPruneTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_prune_removes_connections_older_than_one_day(): void
    {
        Carbon::setTestNow('2026-06-30 12:00:00');

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        $server = Server::query()->create([
            'name' => 'Germany',
            'code' => 'DE',
            'ip' => '10.0.0.5',
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
            'allowed_inbound_ids' => [10],
        ]);

        ActiveConnection::query()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'config_type' => ActiveConnection::CONFIG_TYPE_VLESS,
            'config_id' => 101,
            'protocol' => 'vless',
            'ip' => '198.51.100.10',
            'first_seen' => now()->subDays(2),
            'last_seen' => now()->subDays(2),
        ]);

        ActiveConnection::query()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'config_type' => ActiveConnection::CONFIG_TYPE_VLESS,
            'config_id' => 102,
            'protocol' => 'vless',
            'ip' => '198.51.100.11',
            'first_seen' => now()->subHours(12),
            'last_seen' => now()->subHours(12),
        ]);

        $this->artisan('model:prune', [
            '--model' => [ActiveConnection::class],
        ])->assertSuccessful();

        $this->assertDatabaseMissing('active_connections', [
            'config_id' => 101,
            'ip' => '198.51.100.10',
        ]);
        $this->assertDatabaseHas('active_connections', [
            'config_id' => 102,
            'ip' => '198.51.100.11',
        ]);

        Carbon::setTestNow();
    }

    public function test_active_connection_prune_is_scheduled_hourly(): void
    {
        $events = collect(Schedule::events());

        $event = $events->first(function ($scheduledEvent) {
            return str_contains((string) $scheduledEvent->command, 'model:prune')
                && str_contains((string) $scheduledEvent->command, ActiveConnection::class);
        });

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
    }
}
