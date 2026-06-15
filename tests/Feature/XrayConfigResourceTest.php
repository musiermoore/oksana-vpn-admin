<?php

namespace Tests\Feature;

use App\Http\Resources\XrayConfigResource;
use App\Models\Server;
use App\Models\User;
use App\Models\VlessConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class XrayConfigResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_keeps_hysteria_protocol_label_and_vless_routes(): void
    {
        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        $server = Server::query()->create([
            'name' => 'Germany',
            'code' => 'DE',
            'ip' => '10.0.0.5',
            'type' => Server::TYPE_VLESS,
        ]);

        $config = VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'name' => 'alice-hysteria',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'hysteria-uuid',
            'password' => 'hysteria-password',
            'auth' => 'hysteria-auth',
            'port' => 443,
            'protocol' => 'hysteria',
            'type' => 'udp',
            'encryption' => 'none',
            'security' => 'tls',
            'sni' => 'example.com',
        ]);

        $payload = (new XrayConfigResource($config, 'vless'))->toArray(Request::create('/'));

        $this->assertSame('hysteria', $payload['protocol']);
        $this->assertSame('Hysteria', $payload['protocol_label']);
        $this->assertStringContainsString('/xray-configs/vless/'.$config->id.'/edit', $payload['links']['edit']);
        $this->assertStringContainsString('/xray-configs/vless/'.$config->id.'/enable', $payload['links']['enable']);
    }
}
