<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\SyncXrayInboundsCommand;
use App\Models\Server;
use App\Models\VlessConfig;
use App\Models\XrayInbound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class SyncXrayInboundsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_syncs_panel_inbounds_and_updates_existing_vless_relation(): void
    {
        $server = Server::query()->create([
            'name' => 'Germany',
            'code' => 'DE',
            'ip' => '10.0.0.5',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_active' => true,
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ]);

        $config = VlessConfig::query()->create([
            'server_id' => $server->id,
            'inbound_id' => 11,
            'user_id' => null,
            'name' => 'alice-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => 'uuid-1',
            'port' => 443,
            'protocol' => 'vless',
            'type' => 'tcp',
            'encryption' => 'none',
            'security' => 'reality',
        ]);

        Http::fake([
            'https://panel.test/csrf-token' => Http::response([
                'token' => 'csrf-token-value',
            ], 200, ['Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly']),
            'https://panel.test/' => Http::response(
                '<meta name="csrf-token" content="csrf-token-value">',
                200,
                ['Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly']
            ),
            'https://panel.test/login' => Http::response([], 200, [
                'Set-Cookie' => '3x-ui=test-session; Path=/; HttpOnly',
            ]),
            'https://panel.test/panel/api/inbounds/list' => Http::response([
                'obj' => [
                    [
                        'id' => 11,
                        'protocol' => 'vless',
                        'port' => 443,
                        'remark' => 'Main inbound',
                        'settings' => json_encode(['clients' => []], JSON_UNESCAPED_SLASHES),
                    ],
                    [
                        'id' => 13,
                        'protocol' => 'shadowsocks',
                        'port' => 8443,
                        'remark' => 'SS inbound',
                        'settings' => json_encode(['clients' => []], JSON_UNESCAPED_SLASHES),
                    ],
                ],
            ]),
        ]);

        $this->artisan(SyncXrayInboundsCommand::class)
            ->assertSuccessful();

        $this->assertDatabaseHas('xray_inbounds', [
            'server_id' => $server->id,
            'external_id' => 11,
            'is_active' => true,
            'is_public' => true,
        ]);

        $this->assertDatabaseHas('xray_inbounds', [
            'server_id' => $server->id,
            'external_id' => 13,
        ]);

        $record = \App\Models\XrayInbound::query()
            ->where('server_id', $server->id)
            ->where('external_id', 11)
            ->firstOrFail();

        $this->assertSame('Main inbound', data_get($record->params, 'remark'));

        $config->refresh();

        $this->assertSame((int) $record->getKey(), (int) $config->xray_inbound_id);

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://panel.test/panel/api/inbounds/list');
    }

    public function test_command_marks_deleted_or_empty_inbounds_as_inactive_and_does_not_save_them(): void
    {
        $server = Server::query()->create([
            'name' => 'Germany',
            'code' => 'DE',
            'ip' => '10.0.0.5',
            'panel_link' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'is_active' => true,
            'is_ready' => true,
            'type' => Server::TYPE_VLESS,
        ]);

        XrayInbound::query()->create([
            'server_id' => $server->id,
            'external_id' => 11,
            'is_active' => true,
            'is_public' => true,
            'params' => ['id' => 11, 'protocol' => 'vless', 'remark' => 'Old'],
        ]);

        Http::fake([
            'https://panel.test/csrf-token' => Http::response([
                'token' => 'csrf-token-value',
            ], 200, ['Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly']),
            'https://panel.test/' => Http::response(
                '<meta name="csrf-token" content="csrf-token-value">',
                200,
                ['Set-Cookie' => '3x-ui=bootstrap-session; Path=/; HttpOnly']
            ),
            'https://panel.test/login' => Http::response([], 200, [
                'Set-Cookie' => '3x-ui=test-session; Path=/; HttpOnly',
            ]),
            'https://panel.test/panel/api/inbounds/list' => Http::response([
                'obj' => [[
                    'id' => 11,
                    'protocol' => 'vless',
                    'port' => 443,
                    'remark' => 'Deleted inbound',
                    'settings' => null,
                    'streamSettings' => null,
                ]],
            ]),
        ]);

        $this->artisan(SyncXrayInboundsCommand::class)
            ->assertSuccessful();

        $this->assertDatabaseHas('xray_inbounds', [
            'server_id' => $server->id,
            'external_id' => 11,
            'is_active' => false,
            'params' => null,
        ]);
    }

    public function test_command_is_scheduled_every_five_minutes(): void
    {
        $events = collect(Schedule::events());

        $event = $events->first(function ($scheduledEvent) {
            return str_contains((string) $scheduledEvent->command, 'xray-inbounds:sync');
        });

        $this->assertNotNull($event);
        $this->assertSame('*/5 * * * *', $event->expression);
    }
}
