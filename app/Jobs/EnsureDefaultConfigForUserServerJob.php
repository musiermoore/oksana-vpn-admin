<?php

namespace App\Jobs;

use App\Models\Server;
use App\Models\User;
use App\Models\VlessConfig;
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

        if ($server->is_vless) {
            $this->assignVlessConfig($user, $server);

            return;
        }

        if ($user->configs()->where('server_id', $server->id)->exists()) {
            return;
        }

        $configName = $user->getDefaultConfigNameForServer($server);

        if (! $user->createConfig([
            'name' => $configName,
            'server_id' => $server->id,
            'is_active' => true,
        ])) {
            throw new RuntimeException("Failed to create WireGuard config [{$configName}]");
        }
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }

    private function assignVlessConfig(User $user, Server $server): void
    {
        if ($user->vlessConfigs()->where('server_id', $server->id)->exists()) {
            return;
        }

        $vlessConfig = VlessConfig::query()
            ->where('server_id', $server->id)
            ->whereNull('user_id')
            ->orderBy('id')
            ->first();

        if (! $vlessConfig) {
            throw new RuntimeException(
                "No free VLESS config available for server [{$server->id}] and user [{$user->id}]"
            );
        }

        $vlessConfig->update(['user_id' => $user->id]);
    }
}
