<?php

namespace App\DTOs\Limit;

readonly class LimitData
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
