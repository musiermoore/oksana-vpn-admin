<?php

namespace App\Providers;

use App\Events\UserBalanceDeltaRequested;
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
        UserBalanceDeltaRequested::class => [
            ApplyUserBalanceDelta::class,
        ],
    ];
}
