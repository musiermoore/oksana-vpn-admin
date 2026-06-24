<?php

namespace App\Events;

use App\Models\SupportTicket;

class SupportTicketCreated
{
    public function __construct(
        public readonly SupportTicket $ticket,
    ) {}
}
