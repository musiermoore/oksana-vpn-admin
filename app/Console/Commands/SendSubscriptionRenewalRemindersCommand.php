<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class SendSubscriptionRenewalRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:send-renewal-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send renewal reminders to users with insufficient balance';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $subscriptionService): void
    {
        $subscriptionService->sendRenewalReminders();
    }
}
