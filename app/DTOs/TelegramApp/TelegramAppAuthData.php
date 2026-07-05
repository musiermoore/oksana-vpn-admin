<?php

declare(strict_types=1);

namespace App\DTOs\TelegramApp;

use App\DTOs\Data;

class TelegramAppAuthData extends Data
{
    public function __construct(
        public string $initData,
        public ?string $startParam = null,
    ) {}
}
