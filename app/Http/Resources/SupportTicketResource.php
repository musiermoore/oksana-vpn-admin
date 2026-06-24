<?php

namespace App\Http\Resources;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var SupportTicket $ticket */
        $ticket = $this->resource;
        $telegram = $ticket->user?->telegram;
        $telegramId = $ticket->user?->telegram_id;
        $chatUrl = $telegram
            ? 'https://t.me/'.ltrim($telegram, '@')
            : ($telegramId ? 'tg://user?id='.$telegramId : null);

        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'status' => $ticket->status instanceof SupportTicketStatus ? $ticket->status->value : $ticket->status,
            'status_label' => $ticket->status instanceof SupportTicketStatus ? $ticket->status->label() : (string) $ticket->status,
            'created_at' => optional($ticket->created_at)?->toAtomString(),
            'updated_at' => optional($ticket->updated_at)?->toAtomString(),
            'last_message_at' => optional($ticket->last_message_at)?->toAtomString(),
            'closed_at' => optional($ticket->closed_at)?->toAtomString(),
            'latest_message' => $ticket->relationLoaded('latestMessage') && $ticket->latestMessage
                ? new SupportTicketMessageResource($ticket->latestMessage)
                : null,
            'messages' => $ticket->relationLoaded('messages')
                ? SupportTicketMessageResource::collection($ticket->messages)->resolve()
                : [],
            'user' => $ticket->user ? [
                'id' => $ticket->user->id,
                'name' => $ticket->user->name,
                'telegram' => $ticket->user->telegram,
                'telegram_id' => $ticket->user->telegram_id,
                'chat_url' => $chatUrl,
            ] : null,
            'links' => [
                'show' => route('support-tickets.show', $ticket),
            ],
        ];
    }
}
