<?php

namespace App\Console\Commands;

use App\Jobs\SyncVlessExternalSubscriptionJob;
use App\Models\VlessExternalSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class SyncVlessExternalSubscriptionsCommand extends Command
{
    protected $signature = 'vless-external-subscriptions:sync';

    protected $description = 'Queue synchronization for external VLESS whitelist subscriptions';

    public function handle(): int
    {
        VlessExternalSubscription::query()
            ->where('is_active', true)
            ->pluck('id')
            ->each(fn (int $id) => Bus::dispatch(new SyncVlessExternalSubscriptionJob($id)));

        return self::SUCCESS;
    }
}
