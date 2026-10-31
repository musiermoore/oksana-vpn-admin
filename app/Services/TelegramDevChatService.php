<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendTelegramMessageJob;
use App\Jobs\SendTelegramPhotoJob;

class TelegramDevChatService
{
    public function send(string $message, ?string $parseMode = null): void
    {
        $chatId = (string) config('services.telegram.dev_chat_id', '');

        if ($chatId === '') {
            return;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $message,
        ];
        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
        }

        SendTelegramMessageJob::dispatch($payload)->afterCommit();
    }

    public function sendPhoto(string $contents, string $caption): void
    {
        $chatId = (string) config('services.telegram.dev_chat_id', '');
        if ($chatId === '') {
            return;
        }

        SendTelegramPhotoJob::dispatch($chatId, base64_encode($contents), mb_scrub($caption, 'UTF-8'))->afterCommit();
    }
}
