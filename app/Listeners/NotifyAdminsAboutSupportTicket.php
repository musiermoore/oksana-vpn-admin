<?php

namespace App\Listeners;

use App\Events\SupportTicketCreated;
use App\Events\SupportTicketUserMessageCreated;
use App\Models\User;
use App\Services\TelegramBroadcastService;
use App\Services\TelegramDevChatService;

class NotifyAdminsAboutSupportTicket
{
    public function __construct(
        private readonly TelegramBroadcastService $broadcastService,
        private readonly TelegramDevChatService $devChatService,
    ) {}

    public function handle(SupportTicketCreated|SupportTicketUserMessageCreated $event): void
    {
        $ticket = $event->ticket->loadMissing(['user', 'latestMessage']);
        $message = $this->buildMessage($event, $ticket);
        $ticketUrl = route('telegram-app.pages.support.show', $ticket->id);

        $admins = User::query()
            ->select(['id', 'telegram', 'telegram_id'])
            ->where('is_admin', true)
            ->whereNotNull('telegram_id')
            ->get();

        if ($admins->isNotEmpty()) {
            $this->broadcastService->send($admins, e($message), extra: [
                'reply_markup' => [
                    'inline_keyboard' => [[
                        [
                            'text' => 'Открыть тикет',
                            'url' => $ticketUrl,
                        ],
                    ]],
                ],
            ]);
        }

        $this->devChatService->send($message);
    }

    private function buildMessage(
        SupportTicketCreated|SupportTicketUserMessageCreated $event,
        $ticket,
    ): string
    {
        $title = $event instanceof SupportTicketCreated
            ? "Новый тикет #{$ticket->id}"
            : "Новое сообщение в тикете #{$ticket->id}";

        return implode("\n", array_filter([
            $title,
            $ticket->user?->telegram ? "Пользователь: {$ticket->user->telegram}" : null,
            $ticket->user?->name ? "Имя: {$ticket->user->name}" : null,
            $ticket->subject ? "Тема: {$ticket->subject}" : null,
            $ticket->latestMessage?->message ? "Сообщение: {$ticket->latestMessage->message}" : null,
            "Открыть: ".route('support-tickets.show', $ticket->id),
        ]));
    }
}
