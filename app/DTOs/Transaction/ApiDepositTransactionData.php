<?php

namespace App\DTOs\Transaction;

readonly class ApiDepositTransactionData
{
    public function __construct(
        public int $month,
        public string $bank,
    ) {}
}
