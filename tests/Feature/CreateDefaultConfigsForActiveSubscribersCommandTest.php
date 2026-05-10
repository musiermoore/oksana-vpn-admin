<?php

namespace Tests\Feature;

use App\Jobs\DispatchDefaultConfigsForUserJob;
use App\Jobs\EnsureDefaultConfigForUserServerJob;
use App\Models\Server;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreateDefaultConfigsForActiveSubscribersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_one_user_job_per_active_subscriber(): void
    {
        Queue::fake();

        $activeUser = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        UserSubscription::query()->create([
            'user_id' => $activeUser->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 10,
        ]);

        User::query()->create([
            'name' => 'Bob',
            'telegram' => '@bob',
            'join_at' => now()->toDateString(),
        ]);

        $this->artisan('configs:create-default-for-active-subscribers')
            ->assertSuccessful();

        Queue::assertPushed(DispatchDefaultConfigsForUserJob::class, function (DispatchDefaultConfigsForUserJob $job) use ($activeUser) {
            return $job->userId === $activeUser->id;
        });

        Queue::assertPushed(DispatchDefaultConfigsForUserJob::class, 1);
    }

    public function test_user_job_dispatches_one_job_per_missing_ready_config(): void
    {
        Queue::fake();

        $readyWireGuardServer = Server::query()->create([
            'name' => 'Ready WG',
            'code' => 'RWG',
            'ip' => '10.0.0.1',
            'app_path' => '/opt/app',
            'is_ready' => true,
            'is_vless' => false,
        ]);

        $readyVlessServer = Server::query()->create([
            'name' => 'Ready VLESS',
            'code' => 'RVL',
            'ip' => '10.0.0.2',
            'app_path' => '/opt/app',
            'is_ready' => true,
            'is_vless' => true,
        ]);

        Server::query()->create([
            'name' => 'Not Ready WG',
            'code' => 'NWG',
            'ip' => '10.0.0.3',
            'app_path' => '/opt/app',
            'is_ready' => false,
            'is_vless' => false,
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 10,
        ]);

        (new DispatchDefaultConfigsForUserJob($user->id))->handle();

        Queue::assertPushed(EnsureDefaultConfigForUserServerJob::class, function (EnsureDefaultConfigForUserServerJob $job) use ($user, $readyWireGuardServer) {
            return $job->userId === $user->id && $job->serverId === $readyWireGuardServer->id;
        });

        Queue::assertPushed(EnsureDefaultConfigForUserServerJob::class, function (EnsureDefaultConfigForUserServerJob $job) use ($user, $readyVlessServer) {
            return $job->userId === $user->id && $job->serverId === $readyVlessServer->id;
        });

        Queue::assertPushed(EnsureDefaultConfigForUserServerJob::class, 2);
    }
}
