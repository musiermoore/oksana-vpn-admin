<?php

namespace App\Jobs;

use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class DispatchDefaultConfigsForUserJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public readonly int $userId,
    ) {
        $this->onQueue('configs');
    }

    public function handle(): void
    {
        $user = User::query()
            ->with([
                'configs:id,user_id,server_id',
                'vlessConfigs:id,user_id,server_id',
            ])
            ->find($this->userId);

        if (! $user || ! $user->hasActiveSubscription()) {
            return;
        }

        $servers = Server::query()
            ->where('is_ready', true)
            ->orderBy('id')
            ->get();

        $existingWireGuardServerIds = $user->configs->pluck('server_id')->all();
        $existingVlessServerIds = $user->vlessConfigs->pluck('server_id')->all();

        foreach ($servers as $server) {
            if ($server->is_vless) {
                if (in_array($server->id, $existingVlessServerIds, true)) {
                    continue;
                }
            } elseif (in_array($server->id, $existingWireGuardServerIds, true)) {
                continue;
            }

            EnsureDefaultConfigForUserServerJob::dispatch($user->id, $server->id);
        }
    }
}
