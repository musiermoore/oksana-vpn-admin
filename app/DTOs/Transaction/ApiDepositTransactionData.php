<?php

namespace App\DTOs\Transaction;

use App\Enums\SubscriptionPurchaseType;

readonly class ApiDepositTransactionData
{
    public function __construct(
        public int $month,
        public ?string $returnUrl,
        public SubscriptionPurchaseType $purchaseType = SubscriptionPurchaseType::PERSONAL,
    ) {}
}
