<?php

namespace App\Console\Commands;

use App\Jobs\DispatchDefaultConfigsForUserJob;
use App\Models\Server;
use App\Models\User;
use Illuminate\Console\Command;

class CreateDefaultConfigsForActiveSubscribersCommand extends Command
{
    protected $signature = 'configs:create-default-for-active-subscribers {user_id?}';

    protected $description = 'Dispatch default config provisioning jobs for active subscribers on ready servers';

    public function handle(): int
    {
        $userId = $this->argument('user_id');

        $servers = Server::query()
            ->where('is_active', true)
            ->where('is_ready', true)
            ->with('xrayInbounds')
            ->get();

        $users = User::query()
            ->whereHas('activeSubscription')
            ->when($userId, fn ($query) => $query->whereKey($userId))
            ->with([
                'configs:id,user_id,server_id',
                'vlessConfigs:id,user_id,server_id,xray_inbound_id',
                'vlessConfigs.xrayInbound:id,external_id',
            ])
            ->get();

        $users = $users->filter(function (User $user) use ($servers): bool {
            foreach ($servers as $server) {
                if ($server->isVlessType()) {
                    $allowedInboundIds = $server->getAvailableInboundIdsForUser($user);

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

                    if (collect($allowedInboundIds)->diff($existingInboundIds)->isNotEmpty()) {
                        return true;
                    }

                    continue;
                }

                if (! $user->configs->contains(fn (mixed $config) => (int) $config->server_id === (int) $server->id)) {
                    return true;
                }
            }

            return false;
        })->values();

        foreach ($users as $user) {
            DispatchDefaultConfigsForUserJob::dispatch($user->id);
        }

        $this->info("Dispatched {$users->count()} user jobs.");

        return self::SUCCESS;
    }
}
