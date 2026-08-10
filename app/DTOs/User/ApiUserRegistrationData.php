<?php

declare(strict_types=1);

namespace App\DTOs\User;

use App\DTOs\Data;

class ApiUserRegistrationData extends Data
{
    public function __construct(
        public string $telegramId,
        public ?string $telegram = null,
        public ?string $name = null,
        public ?string $startParam = null,
    ) {}
}
