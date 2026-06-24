<?php

namespace App\Http\Resources;

use App\Enums\SupportTicketSenderType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sender_type' => $this->sender_type instanceof SupportTicketSenderType
                ? $this->sender_type->value
                : $this->sender_type,
            'sender_name' => $this->sender_name,
            'message' => $this->message,
            'created_at' => optional($this->created_at)?->toAtomString(),
        ];
    }
}
