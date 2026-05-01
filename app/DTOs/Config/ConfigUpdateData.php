<?php

namespace App\DTOs\Config;

readonly class ConfigUpdateData
{
    public function __construct(
        public int $userId,
        public ?string $description,
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'description' => $this->description,
        ];
    }
}
