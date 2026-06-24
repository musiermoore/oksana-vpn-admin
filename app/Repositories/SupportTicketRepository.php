<?php

namespace App\Repositories;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class SupportTicketRepository
{
    public function createForUser(User $user, array $attributes): SupportTicket
    {
        return $user->supportTickets()->create($attributes);
    }

    public function update(SupportTicket $ticket, array $attributes): SupportTicket
    {
        $ticket->update($attributes);

        return $ticket->refresh();
    }

    public function findForUser(User $user, int $ticketId): ?SupportTicket
    {
        return SupportTicket::query()
            ->with(['messages', 'user'])
            ->where('user_id', $user->id)
            ->find($ticketId);
    }

    public function getForUser(User $user): Collection
    {
        return SupportTicket::query()
            ->with(['latestMessage', 'messages', 'user'])
            ->where('user_id', $user->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();
    }

    public function getForAdmin(?SupportTicketStatus $status = null): Collection
    {
        return SupportTicket::query()
            ->with(['latestMessage', 'user'])
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 WHEN status = 'answered' THEN 1 ELSE 2 END")
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();
    }

    public function findForAdmin(int $ticketId): ?SupportTicket
    {
        return SupportTicket::query()
            ->with(['messages.user', 'user'])
            ->find($ticketId);
    }
}
