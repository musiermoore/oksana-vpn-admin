<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\TelegramDeliveryException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Laravel\Facades\Telegram;
use Throwable;

final class SendTelegramPhotoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $chatId,
        public readonly string $base64Contents,
        public readonly string $caption,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        try {
            $contents = base64_decode($this->base64Contents, true);
            if ($contents === false) {
                throw new \UnexpectedValueException('Queued Telegram photo has invalid base64 data.');
            }

            Telegram::sendPhoto([
                'chat_id' => $this->chatId,
                'photo' => InputFile::createFromContents($contents, 'health-check-summary.png'),
                'caption' => $this->caption,
            ]);
        } catch (Throwable $throwable) {
            if (TelegramDeliveryException::shouldSkip($throwable)) {
                return;
            }

            report($throwable);
        }
    }
}
