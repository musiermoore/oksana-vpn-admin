<?php

namespace App\DTOs\CurrentPayment;

readonly class CurrentPaymentData
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
