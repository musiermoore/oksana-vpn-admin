<?php

namespace App\DTOs\User;

readonly class ApiUserRegistrationData
{
    public function __construct(
        public string $telegramId,
        public string $telegram,
        public ?string $name,
        public ?string $startParam = null,
    ) {}
}
