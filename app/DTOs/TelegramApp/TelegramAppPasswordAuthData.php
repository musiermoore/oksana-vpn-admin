<?php

declare(strict_types=1);

namespace App\DTOs\TelegramApp;

use App\DTOs\Data;

class TelegramAppPasswordAuthData extends Data
{
    public function __construct(
        public string $login,
        public string $password,
    ) {}
}
