<?php

declare(strict_types=1);

namespace App\DTOs\SupportTicket;

use App\DTOs\Data;
use App\Enums\SupportTicketStatus;

class SupportTicketReplyData extends Data
{
    public function __construct(
        public string $message,
        public ?SupportTicketStatus $status = null,
    ) {}
}
