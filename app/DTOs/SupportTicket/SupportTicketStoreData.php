<?php

namespace App\DTOs\SupportTicket;

readonly class SupportTicketStoreData
{
    public function __construct(
        public ?string $subject,
        public string $message,
    ) {}
}
