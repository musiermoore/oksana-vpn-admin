<?php

namespace App\DTOs\Config;

readonly class ConfigCreateItemData
{
    public function __construct(
        public string $name,
        public int $serverId,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'server_id' => $this->serverId,
        ];
    }
}
