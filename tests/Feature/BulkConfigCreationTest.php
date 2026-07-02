<?php

namespace Tests\Feature;

use App\DTOs\Config\ConfigBulkStoreData;
use App\Models\Server;
use App\Models\User;
use App\Services\Crud\ConfigCrudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class BulkConfigCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_creation_uses_same_name_strategy_as_single_creation_and_waits_between_users(): void
    {
        Sleep::fake();

        $server = Server::query()->create([
            'name' => 'Bulk Server',
            'code' => 'BULK',
            'ip' => '10.0.0.7',
            'type' => Server::TYPE_WIREGUARD_OLD,
            'app_path' => '/opt/app',
        ]);

        $alice = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        $bob = User::query()->create([
            'name' => 'Bob',
            'telegram' => '@bob',
            'join_at' => now()->toDateString(),
        ]);

        $failedConfigs = app(ConfigCrudService::class)->createBulk(new ConfigBulkStoreData($server->id));

        $this->assertSame([], $failedConfigs);
        $this->assertMatchesRegularExpression('/^alice-bulk-server-[a-z]{16}$/', $alice->configs()->firstOrFail()->name);
        $this->assertMatchesRegularExpression('/^bob-bulk-server-[a-z]{16}$/', $bob->configs()->firstOrFail()->name);
        $this->assertDatabaseMissing('configs', ['name' => 'alice_BULK']);
        $this->assertDatabaseMissing('configs', ['name' => 'bob_BULK']);

        Sleep::assertSequence([
            Sleep::sleep(5),
        ]);
    }

    public function test_bulk_creation_rejects_inactive_server(): void
    {
        $server = Server::query()->create([
            'name' => 'Inactive Bulk Server',
            'code' => 'IBS',
            'ip' => '10.0.0.8',
            'type' => Server::TYPE_WIREGUARD_OLD,
            'app_path' => '/opt/app',
            'is_active' => false,
        ]);

        User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => now()->toDateString(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Сервер Inactive Bulk Server отключён.');

        app(ConfigCrudService::class)->createBulk(new ConfigBulkStoreData($server->id));
    }
}
