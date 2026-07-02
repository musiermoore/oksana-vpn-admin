<?php

namespace App\Console\Commands;

use App\Jobs\DispatchDefaultConfigsForUserJob;
use App\Models\Server;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

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
            ->get();

        $users = User::query()
            ->whereHas('activeSubscription')
            ->when($userId, fn ($query) => $query->whereKey($userId))
            ->where(function (Builder $query) use ($servers) {
                $hasMissingRequirement = false;

                foreach ($servers as $server) {
                    if ($server->isVlessType()) {
                        foreach ($server->getAllowedInboundIds() as $inboundId) {
                            $hasMissingRequirement = true;

                            $query->orWhereDoesntHave('vlessConfigs', function (Builder $configQuery) use ($server, $inboundId) {
                                $configQuery
                                    ->where('server_id', $server->id)
                                    ->where('inbound_id', $inboundId);
                            });
                        }

                        continue;
                    }

                    $hasMissingRequirement = true;

                    $query->orWhereDoesntHave('configs', function (Builder $configQuery) use ($server) {
                        $configQuery->where('server_id', $server->id);
                    });
                }

                if (! $hasMissingRequirement) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->select('id')
            ->get();

        foreach ($users as $user) {
            DispatchDefaultConfigsForUserJob::dispatch($user->id);
        }

        $this->info("Dispatched {$users->count()} user jobs.");

        return self::SUCCESS;
    }
}
