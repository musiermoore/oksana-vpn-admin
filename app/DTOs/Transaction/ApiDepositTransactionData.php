<?php

namespace App\DTOs\Transaction;

readonly class ApiDepositTransactionData
{
    public function __construct(
        public float $amount,
        public string $bank,
    ) {}
}
