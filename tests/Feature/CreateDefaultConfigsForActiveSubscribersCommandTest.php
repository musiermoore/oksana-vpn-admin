<?php

namespace Tests\Feature;

use App\Jobs\DispatchDefaultConfigsForUserJob;
use App\Jobs\EnsureDefaultConfigForUserServerJob;
use App\Models\Config;
use App\Models\Server;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\VlessConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class CreateDefaultConfigsForActiveSubscribersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_jobs_for_all_active_subscribers(): void
    {
        Queue::fake();

        $wireGuardServer = Server::query()->create([
            'name' => 'Existing WG',
            'code' => 'EWG',
            'ip' => '10.0.0.10',
            'app_path' => '/opt/app',
            'is_ready' => true,
            'is_vless' => false,
        ]);

        $vlessServer = Server::query()->create([
            'name' => 'Existing VLESS',
            'code' => 'EVL',
            'ip' => '10.0.0.11',
            'app_path' => '/opt/app',
            'is_ready' => true,
            'is_vless' => true,
            'auto_pull_vless_types' => ['tcp'],
        ]);

        $missingBothUser = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        UserSubscription::query()->create([
            'user_id' => $missingBothUser->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 10,
        ]);

        $hasOnlyVlessUser = User::query()->create([
            'name' => 'Bob',
            'telegram' => '@bob',
            'join_at' => now()->toDateString(),
        ]);

        UserSubscription::query()->create([
            'user_id' => $hasOnlyVlessUser->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 10,
        ]);

        VlessConfig::query()->create([
            'server_id' => $vlessServer->id,
            'user_id' => $hasOnlyVlessUser->id,
            'name' => 'vless-only',
            'is_active' => true,
            'enable' => true,
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'port' => 443,
        ]);

        $hasBothUser = User::query()->create([
            'name' => 'Charlie',
            'telegram' => '@charlie',
            'join_at' => now()->toDateString(),
        ]);

        UserSubscription::query()->create([
            'user_id' => $hasBothUser->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 10,
        ]);

        Config::query()->create([
            'user_id' => $hasBothUser->id,
            'server_id' => $wireGuardServer->id,
            'name' => 'wg-existing',
            'description' => null,
            'is_active' => true,
        ]);

        VlessConfig::query()->create([
            'server_id' => $vlessServer->id,
            'user_id' => $hasBothUser->id,
            'name' => 'vless-existing',
            'is_active' => true,
            'enable' => true,
            'uuid' => '22222222-2222-2222-2222-222222222222',
            'port' => 443,
        ]);

        User::query()->create([
            'name' => 'Dana',
            'telegram' => '@dana',
            'join_at' => now()->toDateString(),
        ]);

        $this->artisan('configs:create-default-for-active-subscribers')
            ->assertSuccessful();

        Queue::assertPushed(DispatchDefaultConfigsForUserJob::class, function (DispatchDefaultConfigsForUserJob $job) use ($missingBothUser) {
            return $job->userId === $missingBothUser->id;
        });

        Queue::assertPushed(DispatchDefaultConfigsForUserJob::class, function (DispatchDefaultConfigsForUserJob $job) use ($hasOnlyVlessUser) {
            return $job->userId === $hasOnlyVlessUser->id;
        });

        Queue::assertPushed(DispatchDefaultConfigsForUserJob::class, function (DispatchDefaultConfigsForUserJob $job) use ($hasBothUser) {
            return $job->userId === $hasBothUser->id;
        });

        Queue::assertPushed(DispatchDefaultConfigsForUserJob::class, 3);
    }

    public function test_user_job_dispatches_one_job_per_missing_server_config(): void
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
            'auto_pull_vless_types' => ['tcp'],
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

    public function test_user_job_dispatches_missing_server_pair_when_user_has_other_server_configs(): void
    {
        Queue::fake();

        $latviaWireGuardServer = Server::query()->create([
            'name' => 'Latvia WG',
            'code' => 'LV-WG',
            'ip' => '10.0.0.1',
            'app_path' => '/opt/app',
            'is_ready' => true,
            'is_vless' => false,
        ]);

        $latviaVlessServer = Server::query()->create([
            'name' => 'Latvia VLESS',
            'code' => 'LV-VL',
            'ip' => '10.0.0.2',
            'app_path' => '/opt/app',
            'is_ready' => true,
            'is_vless' => true,
            'auto_pull_vless_types' => ['tcp'],
        ]);

        $finlandVlessServer = Server::query()->create([
            'name' => 'Finland VLESS',
            'code' => 'FI-VL',
            'ip' => '10.0.0.3',
            'app_path' => '/opt/app',
            'is_ready' => true,
            'is_vless' => true,
            'auto_pull_vless_types' => ['tcp'],
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

        Config::query()->create([
            'user_id' => $user->id,
            'server_id' => $latviaWireGuardServer->id,
            'name' => 'latvia-wg',
            'description' => null,
            'is_active' => true,
        ]);

        VlessConfig::query()->create([
            'server_id' => $finlandVlessServer->id,
            'user_id' => $user->id,
            'name' => 'finland-vless',
            'is_active' => true,
            'enable' => true,
            'uuid' => '33333333-3333-3333-3333-333333333333',
            'port' => 443,
        ]);

        (new DispatchDefaultConfigsForUserJob($user->id))->handle();

        Queue::assertPushed(EnsureDefaultConfigForUserServerJob::class, function (EnsureDefaultConfigForUserServerJob $job) use ($user, $latviaVlessServer) {
            return $job->userId === $user->id && $job->serverId === $latviaVlessServer->id;
        });

        Queue::assertNotPushed(EnsureDefaultConfigForUserServerJob::class, function (EnsureDefaultConfigForUserServerJob $job) use ($latviaWireGuardServer) {
            return $job->serverId === $latviaWireGuardServer->id;
        });

        Queue::assertNotPushed(EnsureDefaultConfigForUserServerJob::class, function (EnsureDefaultConfigForUserServerJob $job) use ($finlandVlessServer) {
            return $job->serverId === $finlandVlessServer->id;
        });

        Queue::assertPushed(EnsureDefaultConfigForUserServerJob::class, 1);
    }

    public function test_user_job_dispatches_only_missing_vless_for_server_when_wireguard_exists_on_same_server(): void
    {
        Queue::fake();

        $latviaWireGuardServer = Server::query()->create([
            'name' => 'Latvia WG',
            'code' => 'LV-WG',
            'ip' => '10.0.0.1',
            'app_path' => '/opt/app',
            'is_ready' => true,
            'is_vless' => false,
        ]);

        $latviaVlessServer = Server::query()->create([
            'name' => 'Latvia VLESS',
            'code' => 'LV-VL',
            'ip' => '10.0.0.2',
            'app_path' => '/opt/app',
            'is_ready' => true,
            'is_vless' => true,
            'auto_pull_vless_types' => ['tcp'],
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

        Config::query()->create([
            'user_id' => $user->id,
            'server_id' => $latviaWireGuardServer->id,
            'name' => 'existing-wg',
            'description' => null,
            'is_active' => true,
        ]);

        (new DispatchDefaultConfigsForUserJob($user->id))->handle();

        Queue::assertPushed(EnsureDefaultConfigForUserServerJob::class, function (EnsureDefaultConfigForUserServerJob $job) use ($user, $latviaVlessServer) {
            return $job->userId === $user->id && $job->serverId === $latviaVlessServer->id;
        });

        Queue::assertNotPushed(EnsureDefaultConfigForUserServerJob::class, function (EnsureDefaultConfigForUserServerJob $job) use ($latviaWireGuardServer) {
            return $job->serverId === $latviaWireGuardServer->id;
        });

        Queue::assertPushed(EnsureDefaultConfigForUserServerJob::class, 1);
    }

    public function test_command_is_scheduled_every_five_minutes(): void
    {
        $events = collect(Schedule::events());

        $event = $events->first(function ($scheduledEvent) {
            return str_contains($scheduledEvent->command, 'configs:create-default-for-active-subscribers');
        });

        $this->assertNotNull($event);
        $this->assertSame('*/5 * * * *', $event->expression);
    }
}
