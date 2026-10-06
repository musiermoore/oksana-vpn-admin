<?php

declare(strict_types=1);

namespace App\DTOs\NonRegularPayment;

use App\DTOs\Data;

class NonRegularPaymentData extends Data
{
    public function __construct(
        public float $amount,
        public string $description,
    ) {}

    /** @return array{amount: float, description: string} */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'description' => $this->description,
        ];
    }
}
