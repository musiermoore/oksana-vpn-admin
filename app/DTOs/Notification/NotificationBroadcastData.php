<?php

declare(strict_types=1);

namespace App\DTOs\Notification;

use App\DTOs\Data;
use Illuminate\Http\UploadedFile;

class NotificationBroadcastData extends Data
{
    /**
     * @param  array<int, int>  $userIds
     */
    public function __construct(
        public bool $sendToAll,
        public array $userIds = [],
        public ?string $messageHtml = null,
        public ?UploadedFile $image = null,
    ) {}
}
