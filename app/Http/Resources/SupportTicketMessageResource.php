<?php

namespace App\Http\Resources;

use App\Enums\SupportTicketSenderType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attachments = collect($this->attachments ?? [])->map(function (array $attachment, int $index) use ($request): array {
            $routePrefix = str_starts_with($request->path(), 'public/') ? 'public' : 'telegram-app';
            $attachment['url'] = $request->is('support-tickets/*')
                ? route('support-tickets.attachments.download', [
                    'ticketId' => $this->support_ticket_id,
                    'messageId' => $this->id,
                    'attachmentIndex' => $index,
                ])
                : '/'.$routePrefix.'/support/tickets/'.$this->support_ticket_id.'/attachments/'.$this->id.'/'.$index;

            unset($attachment['path']);

            return $attachment;
        })->all();

        return [
            'id' => $this->id,
            'sender_type' => $this->sender_type instanceof SupportTicketSenderType
                ? $this->sender_type->value
                : $this->sender_type,
            'sender_name' => $this->sender_name,
            'message' => $this->message,
            'attachments' => $attachments,
            'created_at' => optional($this->created_at)?->toAtomString(),
        ];
    }
}
