<?php

namespace App\DTOs\Config;

readonly class ConfigCreateItemData
{
    public function __construct(
        public int $serverId,
        public ?string $description,
    ) {}
}
