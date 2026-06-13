<?php

namespace App\Jobs;

use App\Models\Server;
use App\Models\User;
use App\Services\XuiConfigServiceFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class EnsureDefaultConfigForUserServerJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $userId,
        public readonly int $serverId,
    ) {
        $this->onQueue('configs');
    }

    public function handle(): void
    {
        $user = User::query()
            ->with(['activeSubscription'])
            ->find($this->userId);
        $server = Server::query()->find($this->serverId);

        if (! $user || ! $server || ! $server->is_ready || ! $user->hasActiveSubscription()) {
            return;
        }

        if ($server->isVlessType()) {
            $this->assignVlessConfig($user, $server);

            return;
        }

        if ($user->configs()->where('server_id', $server->id)->exists()) {
            return;
        }

        $configName = $user->getDefaultConfigNameForServer($server);

        $user->createConfigOrFail([
            'name' => $configName,
            'server_id' => $server->id,
            'is_active' => true,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }

    private function assignVlessConfig(User $user, Server $server): void
    {
        if ($server->getAllowedInboundIds() === []) {
            return;
        }

        XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server)->createClientsOnAllowedInbounds($user);
    }
}
