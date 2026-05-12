<?php

namespace App\Console\Commands;

use App\Jobs\DispatchDefaultConfigsForUserJob;
use App\Models\User;
use Illuminate\Console\Command;

class CreateDefaultConfigsForActiveSubscribersCommand extends Command
{
    protected $signature = 'configs:create-default-for-active-subscribers {user_id?}';

    protected $description = 'Dispatch default config provisioning jobs for active subscribers on ready servers';

    public function handle(): int
    {
        $userId = $this->argument('user_id');

        $users = User::query()
            ->whereHas('activeSubscription')
            ->when($userId, fn ($query) => $query->whereKey($userId))
            ->select('id')
            ->get();

        foreach ($users as $user) {
            DispatchDefaultConfigsForUserJob::dispatch($user->id);
        }

        $this->info("Dispatched {$users->count()} user jobs.");

        return self::SUCCESS;
    }
}
