<?php

namespace App\Services;

use App\Models\User;
use App\Services\Subscriptions\UserSubscriptionService;

class VlessSubscriptionService
{
    public function __construct(private readonly User $user) {}

    public function getAllSubscriptions(): string
    {
        return app(UserSubscriptionService::class)
            ->build($this->user, 'uri')
            ->content;
    }
}
