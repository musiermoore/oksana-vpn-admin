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

        $wireGuardServer = Server::query()
            ->where('is_ready', true)
            ->where('is_vless', false)
            ->orderBy('id')
            ->first();

        $vlessServer = Server::query()
            ->where('is_ready', true)
            ->where('is_vless', true)
            ->orderBy('id')
            ->first();

        if ($user->configs->isEmpty() && $wireGuardServer) {
            EnsureDefaultConfigForUserServerJob::dispatch($user->id, $wireGuardServer->id);
        }

        if ($user->vlessConfigs->isEmpty() && $vlessServer) {
            EnsureDefaultConfigForUserServerJob::dispatch($user->id, $vlessServer->id);
        }
    }
}
