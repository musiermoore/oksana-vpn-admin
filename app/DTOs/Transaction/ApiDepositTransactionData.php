<?php

declare(strict_types=1);

namespace App\DTOs\Transaction;

use App\DTOs\Data;
use App\Enums\SubscriptionPurchaseType;

class ApiDepositTransactionData extends Data
{
    public function __construct(
        public int $month,
        public ?string $returnUrl = null,
        public SubscriptionPurchaseType $purchaseType = SubscriptionPurchaseType::PERSONAL,
    ) {}
}
