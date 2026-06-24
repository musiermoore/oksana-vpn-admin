<?php

namespace App\Services;

class TelegramMiniAppLinkService
{
    public function ticket(int $ticketId): string
    {
        $botUsername = trim((string) config('services.telegram.bot_username', ''), "@ \t\n\r\0\x0B");

        if ($botUsername === '') {
            return route('telegram-app.pages.support.show', $ticketId);
        }

        return sprintf(
            'https://t.me/%s?startapp=%s',
            $botUsername,
            rawurlencode("ticket_{$ticketId}")
        );
    }
}
