<?php

declare(strict_types=1);

namespace App\Support;

use Telegram\Bot\Exceptions\TelegramResponseException;
use Throwable;

class TelegramDeliveryException
{
    public static function shouldSkip(Throwable $throwable): bool
    {
        if (! $throwable instanceof TelegramResponseException) {
            return false;
        }

        $message = mb_strtolower($throwable->getMessage());
        $statusCode = $throwable->getHttpStatusCode();

        return ($statusCode === 400 && str_contains($message, 'chat not found'))
            || ($statusCode === 403 && str_contains($message, 'bot was blocked by the user'));
    }
}
