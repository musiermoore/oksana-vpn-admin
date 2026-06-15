<?php

namespace App\DTOs\User;

readonly class UserData
{
    public function __construct(
        public string $name,
        public string $telegram,
        public ?string $description,
        public string $joinAt,
        public bool $isActive = true,
        public bool $createConfigs = false,
        public int $maxDevices = 0,
        public int $trafficLimitBytes = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'telegram' => $this->telegram,
            'description' => $this->description,
            'join_at' => $this->joinAt,
            'is_active' => $this->isActive,
            'max_devices' => $this->maxDevices,
            'traffic_limit_bytes' => $this->trafficLimitBytes,
        ];
    }
}
