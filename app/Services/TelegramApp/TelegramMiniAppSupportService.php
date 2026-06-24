<?php

namespace App\Services\TelegramApp;

use App\Enums\SupportTicketSenderType;
use App\Enums\SupportTicketStatus;
use App\DTOs\SupportTicket\SupportTicketReplyData;
use App\DTOs\SupportTicket\SupportTicketStoreData;
use App\Events\SupportTicketCreated;
use App\Events\SupportTicketUserMessageCreated;
use App\Models\SupportTicket;
use App\Models\User;
use App\Repositories\SupportTicketMessageRepository;
use App\Repositories\SupportTicketRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramMiniAppSupportService
{
    public function __construct(
        private readonly SupportTicketRepository $tickets,
        private readonly SupportTicketMessageRepository $messages,
    ) {}

    public function listForUser(User $user): Collection
    {
        return $this->tickets->getForUser($user);
    }

    public function findForUser(User $user, int $ticketId): ?SupportTicket
    {
        return $this->tickets->findForUser($user, $ticketId);
    }

    public function listForAdmin(?SupportTicketStatus $status = null): Collection
    {
        return $this->tickets->getForAdmin($status);
    }

    public function findForAdmin(int $ticketId): ?SupportTicket
    {
        return $this->tickets->findForAdmin($ticketId);
    }

    public function create(User $user, SupportTicketStoreData $data): SupportTicket
    {
        return DB::transaction(function () use ($user, $data) {
            $ticket = $this->tickets->createForUser($user, [
                'subject' => $data->subject,
                'status' => SupportTicketStatus::Open,
                'last_message_at' => now(),
            ]);

            $this->messages->createForTicket($ticket, [
                'user_id' => $user->id,
                'sender_type' => SupportTicketSenderType::User,
                'sender_name' => $user->name,
                'message' => $data->message,
            ]);

            $ticket = $this->tickets->findForAdmin((int) $ticket->id) ?? $ticket->refresh();
            event(new SupportTicketCreated($ticket));

            return $ticket;
        });
    }

    public function addUserMessage(User $user, SupportTicket $ticket, SupportTicketReplyData $data): SupportTicket
    {
        return DB::transaction(function () use ($user, $ticket, $data) {
            $this->messages->createForTicket($ticket, [
                'user_id' => $user->id,
                'sender_type' => SupportTicketSenderType::User,
                'sender_name' => $user->name,
                'message' => $data->message,
            ]);

            $ticket = $this->tickets->update($ticket, [
                'status' => SupportTicketStatus::Open,
                'last_message_at' => now(),
                'closed_at' => null,
            ]);

            $ticket = $this->tickets->findForAdmin((int) $ticket->id) ?? $ticket;
            event(new SupportTicketUserMessageCreated($ticket));

            return $ticket;
        });
    }

    public function addAdminReply(User $admin, SupportTicket $ticket, SupportTicketReplyData $data): SupportTicket
    {
        return DB::transaction(function () use ($admin, $ticket, $data) {
            $this->messages->createForTicket($ticket, [
                'user_id' => $admin->id,
                'sender_type' => SupportTicketSenderType::Admin,
                'sender_name' => $admin->name,
                'message' => $data->message,
            ]);

            $ticket = $this->tickets->update($ticket, [
                'status' => $data->status ?? SupportTicketStatus::Answered,
                'last_message_at' => now(),
                'closed_at' => ($data->status === SupportTicketStatus::Closed) ? now() : null,
            ]);

            $ticket = $this->tickets->findForAdmin((int) $ticket->id) ?? $ticket;

            $this->notifyUserAboutAdminReply($ticket, $data->message);

            return $ticket;
        });
    }

    private function notifyUserAboutAdminReply(SupportTicket $ticket, string $message): void
    {
        $telegramId = (string) ($ticket->user?->telegram_id ?? '');
        $botToken = (string) config('services.telegram.bot_token', '');

        if ($telegramId === '' || $botToken === '') {
            return;
        }

        rescue(function () use ($telegramId, $ticket, $message) {
            $ticketUrl = route('telegram-app.pages.support.show', $ticket->id);

            Telegram::sendMessage([
                'chat_id' => $telegramId,
                'text' => "Ответ по тикету #{$ticket->id}\n\n{$message}\n\nОткрыть тикет: {$ticketUrl}",
                'reply_markup' => json_encode([
                    'inline_keyboard' => [[
                        [
                            'text' => 'Открыть тикет',
                            'url' => $ticketUrl,
                        ],
                    ]],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }, report: false);
    }
}
