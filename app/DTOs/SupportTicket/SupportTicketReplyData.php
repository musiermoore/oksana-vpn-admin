<?php

namespace App\DTOs\SupportTicket;

use App\Enums\SupportTicketStatus;

readonly class SupportTicketReplyData
{
    public function __construct(
        public string $message,
        public ?SupportTicketStatus $status = null,
    ) {}
}
