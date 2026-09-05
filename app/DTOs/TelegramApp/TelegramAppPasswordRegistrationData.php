<?php

declare(strict_types=1);

namespace App\DTOs\TelegramApp;

use App\DTOs\Data;

class TelegramAppPasswordRegistrationData extends Data
{
    public function __construct(
        public string $name,
        public string $login,
        public string $password,
    ) {}
}
