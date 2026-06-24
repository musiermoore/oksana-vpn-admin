<?php

namespace App\Listeners;

use App\Events\SupportTicketCreated;
use App\Models\User;
use App\Services\TelegramBroadcastService;
use App\Services\TelegramDevChatService;

class NotifyAdminsAboutSupportTicket
{
    public function __construct(
        private readonly TelegramBroadcastService $broadcastService,
        private readonly TelegramDevChatService $devChatService,
    ) {}

    public function handle(SupportTicketCreated $event): void
    {
        $ticket = $event->ticket->loadMissing('user');
        $message = $this->buildMessage($ticket);

        $admins = User::query()
            ->select(['id', 'telegram', 'telegram_id'])
            ->where('is_admin', true)
            ->whereNotNull('telegram_id')
            ->get();

        if ($admins->isNotEmpty()) {
            $this->broadcastService->send($admins, e($message));
        }

        $this->devChatService->send($message);
    }

    private function buildMessage($ticket): string
    {
        return implode("\n", array_filter([
            "Новый тикет #{$ticket->id}",
            $ticket->user?->telegram ? "Пользователь: {$ticket->user->telegram}" : null,
            $ticket->user?->name ? "Имя: {$ticket->user->name}" : null,
            $ticket->subject ? "Тема: {$ticket->subject}" : null,
            "Открыть: ".route('support-tickets.show', $ticket->id),
        ]));
    }
}
