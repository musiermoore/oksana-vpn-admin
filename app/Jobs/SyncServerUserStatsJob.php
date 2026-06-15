<?php

namespace App\Jobs;

use App\Models\Server;
use App\Services\SubscriptionMetadataService;
use App\Services\XuiUserTrafficSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncServerUserStatsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 240;

    public function __construct(
        public readonly int $serverId,
    ) {
        $this->onQueue('xui-sync');
    }

    public function uniqueId(): string
    {
        return 'stats:'.$this->serverId;
    }

    public function handle(
        XuiUserTrafficSyncService $syncService,
        SubscriptionMetadataService $metadataService,
    ): void {
        $server = Server::query()->find($this->serverId);

        if (! $server) {
            return;
        }

        foreach ($syncService->syncServer($server) as $userId) {
            $metadataService->forgetCache($userId);
        }
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
