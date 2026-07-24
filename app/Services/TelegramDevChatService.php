<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendTelegramMessageJob;

class TelegramDevChatService
{
    public function send(string $message): void
    {
        $chatId = (string) config('services.telegram.dev_chat_id', '');

        if ($chatId === '') {
            return;
        }

        SendTelegramMessageJob::dispatch([
            'chat_id' => $chatId,
            'text' => $message,
        ])->afterCommit();
    }
}
