<?php

namespace Tests\Feature;

use App\Jobs\EnsureDefaultConfigForUserServerJob;
use App\Models\Server;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\VlessConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureDefaultConfigForUserServerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_one_wireguard_config_for_ready_server(): void
    {
        $server = Server::query()->create([
            'name' => 'Ready WG',
            'code' => 'RWG',
            'ip' => '10.0.0.1',
            'app_path' => '/opt/app',
            'is_ready' => true,
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

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertDatabaseHas('configs', [
            'user_id' => $user->id,
            'server_id' => $server->id,
            'name' => 'alice_RWG',
        ]);
    }

    public function test_job_assigns_one_vless_config_for_ready_server(): void
    {
        $server = Server::query()->create([
            'name' => 'Ready VLESS',
            'code' => 'RVL',
            'ip' => '10.0.0.2',
            'app_path' => '/opt/app',
            'is_ready' => true,
            'is_vless' => true,
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

        $vlessConfig = VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => null,
            'name' => 'free-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => '33333333-3333-3333-3333-333333333333',
        ]);

        (new EnsureDefaultConfigForUserServerJob($user->id, $server->id))->handle();

        $this->assertDatabaseHas('vless_configs', [
            'id' => $vlessConfig->id,
            'user_id' => $user->id,
            'server_id' => $server->id,
        ]);
    }
}
