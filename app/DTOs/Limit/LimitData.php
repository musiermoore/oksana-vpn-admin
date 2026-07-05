<?php

declare(strict_types=1);

namespace App\DTOs\Limit;

use App\DTOs\Data;

class LimitData extends Data
{
    public function __construct(
        public int $configId,
        public int $amount,
    ) {}

    public function toArray(): array
    {
        return [
            'config_id' => $this->configId,
            'amount' => $this->amount,
        ];
    }
}
