<?php

declare(strict_types=1);

namespace App\DTOs\SubscriptionDebug;

use App\DTOs\Data;

final class StoreSubscriptionDebugData extends Data
{
    public function __construct(
        public string $type,
        public string $body,
    ) {}
}
