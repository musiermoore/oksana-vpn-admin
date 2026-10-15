<?php

declare(strict_types=1);

namespace App\Services\TelegramApp;

use App\Repositories\SupportTicketRepository;
use Illuminate\Support\Facades\Storage;

class SupportTicketMaintenanceService
{
    public function __construct(
        private readonly SupportTicketRepository $tickets,
    ) {}

    public function run(): void
    {
        $now = now();
        $fileRetentionCutoff = $now->copy()->subWeek();

        $this->tickets->closeInactiveTickets(
            cutoff: $now->copy()->subDays(3),
            closedAt: $now,
        );

        foreach ($this->tickets->closedTicketsForAttachmentCleanup($fileRetentionCutoff) as $ticket) {
            Storage::disk('local')->deleteDirectory("support-tickets/{$ticket->id}");
        }

        $this->tickets->clearAttachmentsForClosedTicketsBefore($fileRetentionCutoff);
    }
}
