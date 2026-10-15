<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

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

    public function closeInactiveTickets(CarbonInterface $cutoff, CarbonInterface $closedAt): int
    {
        return SupportTicket::query()
            ->whereIn('status', [SupportTicketStatus::Open->value, SupportTicketStatus::Answered->value])
            ->whereNotNull('last_message_at')
            ->where('last_message_at', '<=', $cutoff)
            ->update([
                'status' => SupportTicketStatus::Closed->value,
                'closed_at' => $closedAt,
                'updated_at' => $closedAt,
            ]);
    }

    public function closedTicketsForAttachmentCleanup(CarbonInterface $cutoff): LazyCollection
    {
        return SupportTicket::query()
            ->where('status', SupportTicketStatus::Closed->value)
            ->whereRaw('COALESCE(closed_at, updated_at) <= ?', [$cutoff])
            ->orderBy('id')
            ->cursor();
    }

    public function clearAttachmentsForClosedTicketsBefore(CarbonInterface $cutoff): int
    {
        return SupportTicketMessage::query()
            ->whereNotNull('attachments')
            ->whereHas('ticket', fn ($query) => $query
                ->where('status', SupportTicketStatus::Closed->value)
                ->whereRaw('COALESCE(support_tickets.closed_at, support_tickets.updated_at) <= ?', [$cutoff]))
            ->update(['attachments' => null]);
    }
}
