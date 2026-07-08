<?php

namespace App\Console\Commands;

use App\Jobs\SyncVlessExternalSubscriptionJob;
use App\Models\VlessExternalSubscription;
use Illuminate\Console\Command;

class SyncVlessExternalSubscriptionsCommand extends Command
{
    protected $signature = 'vless-external-subscriptions:sync';

    protected $description = 'Queue synchronization for external VLESS whitelist subscriptions';

    public function handle(): int
    {
        VlessExternalSubscription::query()
            ->where('is_active', true)
            ->pluck('id')
            ->each(fn (int $id) => SyncVlessExternalSubscriptionJob::dispatch($id));

        return self::SUCCESS;
    }
}
