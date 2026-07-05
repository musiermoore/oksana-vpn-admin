<?php

declare(strict_types=1);

namespace App\DTOs\User;

use App\DTOs\Data;

class UserData extends Data
{
    public function __construct(
        public string $name,
        public string $joinAt,
        public ?string $telegram = null,
        public ?string $description = null,
        public bool $isActive = true,
        public bool $createConfigs = false,
        public int $maxDevices = 0,
        public int $trafficLimitBytes = 0,
    ) {}

    public function toArray(): array
    {
        $telegram = $this->telegram !== null ? trim($this->telegram) : null;

        return [
            'name' => $this->name,
            'telegram' => $telegram !== '' ? $telegram : null,
            'description' => $this->description,
            'join_at' => $this->joinAt,
            'is_active' => $this->isActive,
            'max_devices' => $this->maxDevices,
            'traffic_limit_bytes' => $this->trafficLimitBytes,
        ];
    }
}
