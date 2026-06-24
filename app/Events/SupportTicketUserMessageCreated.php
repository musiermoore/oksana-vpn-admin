<?php

namespace App\Events;

use App\Models\SupportTicket;

class SupportTicketUserMessageCreated
{
    public function __construct(
        public readonly SupportTicket $ticket,
    ) {}
}
