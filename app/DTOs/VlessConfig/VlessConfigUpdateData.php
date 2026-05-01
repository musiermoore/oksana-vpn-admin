<?php

namespace App\DTOs\VlessConfig;

readonly class VlessConfigUpdateData
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
