<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConfigCreationByServerTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_uses_wireguard_agent_service_for_modern_wireguard_servers(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
            'join_at' => now()->toDateString(),
        ]);
        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);
        $server = Server::query()->create([
            'name' => 'Modern WG',
            'code' => 'MWG',
            'ip' => '10.0.0.5',
            'type' => Server::TYPE_WIREGUARD,
            'app_path' => '/opt/app',
            'panel_link' => 'https://agent.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
        ]);

        Http::fake([
            'https://agent.test/status' => Http::response([
                'installed' => true,
            ]),
            'https://agent.test/clients' => Http::response([
                'success' => true,
            ]),
            'https://agent.test/clients/*/config' => Http::response('[Interface]'."\n".'Address = 10.0.0.2/32'),
        ]);

        $response = $this->actingAs($admin)->post(route('configs.store'), [
            'user_id' => $user->id,
            'configs' => [
                [
                    'server_id' => $server->id,
                    'description' => 'Agent config',
                ],
            ],
        ]);

        $response->assertRedirect(route('configs.index'));

        $config = $user->configs()->where('server_id', $server->id)->firstOrFail();

        Http::assertSentCount(3);
        $this->assertTrue(File::exists($config->path));
    }

    public function test_endpoint_installs_wireguard_agent_synchronously_before_creating_client_when_needed(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
            'join_at' => now()->toDateString(),
        ]);
        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);
        $server = Server::query()->create([
            'name' => 'Modern WG',
            'code' => 'MWG',
            'ip' => '10.0.0.5',
            'type' => Server::TYPE_WIREGUARD,
            'app_path' => '/opt/app',
            'panel_link' => 'https://agent.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
        ]);

        Http::fake([
            'https://agent.test/status' => Http::response([
                'installed' => false,
            ]),
            'https://agent.test/install' => Http::response([
                'success' => true,
            ]),
            'https://agent.test/clients' => Http::response([
                'success' => true,
            ]),
            'https://agent.test/clients/*/config' => Http::response('[Interface]'."\n".'Address = 10.0.0.2/32'),
        ]);

        $response = $this->actingAs($admin)->post(route('configs.store'), [
            'user_id' => $user->id,
            'configs' => [
                [
                    'server_id' => $server->id,
                    'description' => 'Agent config',
                ],
            ],
        ]);

        $response->assertRedirect(route('configs.index'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://agent.test/install'
                && $request->method() === 'POST';
        });
    }

    public function test_endpoint_keeps_legacy_wireguard_flow_for_wireguard_old_servers(): void
    {
        Http::fake();

        $admin = User::query()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
            'join_at' => now()->toDateString(),
        ]);
        $user = User::query()->create([
            'name' => 'Bob',
            'telegram' => '@bob',
            'join_at' => now()->toDateString(),
        ]);
        $server = Server::query()->create([
            'name' => 'Legacy WG',
            'code' => 'LWG',
            'ip' => '10.0.0.6',
            'type' => Server::TYPE_WIREGUARD_OLD,
            'app_path' => '/opt/app',
        ]);

        $response = $this->actingAs($admin)->post(route('configs.store'), [
            'user_id' => $user->id,
            'configs' => [
                [
                    'server_id' => $server->id,
                    'description' => 'Legacy config',
                ],
            ],
        ]);

        $response->assertRedirect(route('configs.index'));

        $this->assertDatabaseHas('configs', [
            'user_id' => $user->id,
            'server_id' => $server->id,
        ]);

        Http::assertNothingSent();
    }
}
