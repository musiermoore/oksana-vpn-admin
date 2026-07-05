<?php

declare(strict_types=1);

namespace App\DTOs\ExtraPayment;

use App\DTOs\Data;

class ExtraPaymentData extends Data
{
    public function __construct(
        public int $userId,
        public int $currentPaymentId,
        public float $amount,
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'current_payment_id' => $this->currentPaymentId,
            'amount' => $this->amount,
        ];
    }
}
