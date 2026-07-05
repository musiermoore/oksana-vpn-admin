<?php

declare(strict_types=1);

namespace App\DTOs\CurrentPayment;

use App\DTOs\Data;

class CurrentPaymentData extends Data
{
    public function __construct(
        public string $startDate,
        public string $endDate,
        public float $amount,
    ) {}

    public function toArray(): array
    {
        return [
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'amount' => $this->amount,
        ];
    }
}
