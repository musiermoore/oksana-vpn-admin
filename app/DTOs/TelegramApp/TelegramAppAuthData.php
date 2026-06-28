<?php

namespace App\DTOs\TelegramApp;

readonly class TelegramAppAuthData
{
    public function __construct(
        public string $initData,
        public ?string $startParam = null,
    ) {}
}
