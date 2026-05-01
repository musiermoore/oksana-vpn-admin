<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class RenewSubscriptionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:renew';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Renew subscriptions for users with enough balance';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $subscriptionService): void
    {
        $subscriptionService->renewEligibleSubscriptions();
    }
}
