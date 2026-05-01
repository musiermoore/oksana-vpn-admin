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
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'telegram' => $this->telegram,
            'description' => $this->description,
            'join_at' => $this->joinAt,
            'is_active' => $this->isActive,
        ];
    }
}
