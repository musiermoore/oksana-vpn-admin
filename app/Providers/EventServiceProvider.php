<?php

namespace App\Providers;

use App\Events\TransactionApproved;
use App\Events\UserBalanceDeltaRequested;
use App\Listeners\ActivateSubscriptionAfterTransactionApproval;
use App\Listeners\ApplyUserBalanceDelta;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        TransactionApproved::class => [
            ActivateSubscriptionAfterTransactionApproval::class,
        ],
        UserBalanceDeltaRequested::class => [
            ApplyUserBalanceDelta::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
