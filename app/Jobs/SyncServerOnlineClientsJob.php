<?php

namespace App\Jobs;

use App\Models\Server;
use App\Services\DeviceLimitService;
use App\Services\SubscriptionMetadataService;
use App\Services\XuiConnectionSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncServerOnlineClientsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 55;

    public function __construct(
        public readonly int $serverId,
    ) {
        $this->onQueue('xui-sync');
    }

    public function uniqueId(): string
    {
        return 'online:'.$this->serverId;
    }

    public function handle(
        XuiConnectionSyncService $syncService,
        DeviceLimitService $deviceLimitService,
        SubscriptionMetadataService $metadataService,
    ): void {
        $server = Server::query()->find($this->serverId);

        if (! $server) {
            return;
        }

        $userIds = $syncService->syncServer($server);

        foreach ($userIds as $userId) {
            $metadataService->forgetCache($userId);
            $deviceLimitService->enforceForUser($userId);
        }
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
