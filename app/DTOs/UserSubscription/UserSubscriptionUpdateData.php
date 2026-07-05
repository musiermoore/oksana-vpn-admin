<?php

declare(strict_types=1);

namespace App\DTOs\UserSubscription;

use App\DTOs\Data;

class UserSubscriptionUpdateData extends Data
{
    public function __construct(
        public string $startDate,
        public string $endDate,
    ) {}
}
