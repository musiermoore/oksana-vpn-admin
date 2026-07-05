<?php

declare(strict_types=1);

namespace App\DTOs\Config;

use App\DTOs\Data;

class ConfigUpdateData extends Data
{
    public function __construct(
        public int $userId,
        public ?string $description = null,
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'description' => $this->description,
        ];
    }
}
