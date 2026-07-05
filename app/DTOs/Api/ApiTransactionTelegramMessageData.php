<?php

declare(strict_types=1);

namespace App\DTOs\Api;

use App\DTOs\Data;

class ApiTransactionTelegramMessageData extends Data
{
    public function __construct(
        public int $telegramChatId,
        public int $telegramMessageId,
    ) {}
}
