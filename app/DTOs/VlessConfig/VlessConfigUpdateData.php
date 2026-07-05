<?php

declare(strict_types=1);

namespace App\DTOs\VlessConfig;

use App\DTOs\Data;

class VlessConfigUpdateData extends Data
{
    public function __construct(
        public int $userId,
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
        ];
    }
}
