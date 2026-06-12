<?php

namespace App\Services;

use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramDevChatService
{
    public function send(string $message): void
    {
        $chatId = (string) config('services.telegram.dev_chat_id', '');

        if ($chatId === '') {
            return;
        }

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
        ]);
    }
}
