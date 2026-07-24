<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Telegram\Bot\Laravel\Facades\Telegram;
use Throwable;

class EditTelegramMessageTextJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly array $payload,
    ) {
        $this->onQueue('telegram');
    }

    public function handle(): void
    {
        try {
            Telegram::editMessageText($this->payload);
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }
}
