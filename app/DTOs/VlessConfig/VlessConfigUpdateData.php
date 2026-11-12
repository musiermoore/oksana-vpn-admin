<?php

declare(strict_types=1);

namespace App\DTOs\VlessConfig;

use App\DTOs\Data;

class VlessConfigUpdateData extends Data
{
    public function __construct(
        public int $userId,
        public ?int $groupId = null,
        public ?string $subtitle = null,
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'group_id' => $this->groupId,
            'subtitle' => $this->subtitle,
        ];
    }
}
