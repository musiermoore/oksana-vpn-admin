<?php

namespace App\Listeners;

use App\Events\UserBalanceDeltaRequested;
use App\Models\User;

class ApplyUserBalanceDelta
{
    public function handle(UserBalanceDeltaRequested $event): void
    {
        if ($event->userId <= 0 || $event->amount == 0.0) {
            return;
        }

        User::query()
            ->whereKey($event->userId)
            ->increment('balance', $event->amount);
    }
}
