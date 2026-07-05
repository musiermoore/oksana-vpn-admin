<?php

declare(strict_types=1);

namespace App\DTOs\SupportTicket;

use App\DTOs\Data;

class SupportTicketStoreData extends Data
{
    public function __construct(
        public string $message,
        public ?string $subject = null,
    ) {}
}
