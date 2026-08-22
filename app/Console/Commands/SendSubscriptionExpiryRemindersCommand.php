<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SubscriptionExpiryNotificationService;
use Illuminate\Console\Command;

class SendSubscriptionExpiryRemindersCommand extends Command
{
    protected $signature = 'subscriptions:send-expiry-reminders';

    protected $description = 'Send Telegram reminders before subscription expiry';

    public function handle(SubscriptionExpiryNotificationService $notifications): int
    {
        $notifications->sendDueNotifications();

        return self::SUCCESS;
    }
}
