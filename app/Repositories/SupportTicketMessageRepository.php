<?php

namespace App\Repositories;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;

class SupportTicketMessageRepository
{
    public function createForTicket(SupportTicket $ticket, array $attributes): SupportTicketMessage
    {
        return $ticket->messages()->create($attributes);
    }
}
