<?php

declare(strict_types=1);

namespace App\DTOs\Transaction;

use App\DTOs\Data;

class TransactionData extends Data
{
    public function __construct(
        public int $userId,
        public int $typeId,
        public float $amount,
        public ?string $description = null,
        public bool $isApproved = false,
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'type_id' => $this->typeId,
            'amount' => $this->amount,
            'description' => $this->description,
            'is_approved' => $this->isApproved,
        ];
    }
}
