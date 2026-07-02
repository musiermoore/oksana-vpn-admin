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
                'vlessConfigs:id,user_id,server_id,inbound_id,type',
            ])
            ->find($this->userId);

        if (! $user || ! $user->hasActiveSubscription()) {
            return;
        }

        $servers = Server::query()
            ->where('is_active', true)
            ->where('is_ready', true)
            ->orderBy('id')
            ->get();

        $existingWireGuardServerIds = $user->configs->pluck('server_id')->all();
        foreach ($servers as $server) {
            if ($server->isVlessType()) {
                $allowedInboundIds = $server->getAllowedInboundIds();

                if ($allowedInboundIds === []) {
                    continue;
                }

                $existingInboundIds = $user->vlessConfigs
                    ->where('server_id', $server->id)
                    ->pluck('inbound_id')
                    ->filter(fn (mixed $inboundId) => $inboundId !== null)
                    ->map(fn (mixed $inboundId) => (int) $inboundId)
                    ->unique()
                    ->values()
                    ->all();

                if (collect($allowedInboundIds)->diff($existingInboundIds)->isEmpty()) {
                    continue;
                }

                EnsureDefaultConfigForUserServerJob::dispatch($user->id, $server->id);

                continue;
            }

            if (in_array($server->id, $existingWireGuardServerIds, true)) {
                continue;
            }

            EnsureDefaultConfigForUserServerJob::dispatch($user->id, $server->id);
        }
    }
}
