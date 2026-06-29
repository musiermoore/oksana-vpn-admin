<?php

namespace App\Http\Resources\TelegramApp;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'months' => $this->months,
            'days' => $this->days,
            'price' => (float) $this->price,
            'status' => $this->status,
            'activated_at' => optional($this->activated_at)?->toAtomString(),
            'expires_at' => optional($this->expires_at)?->toAtomString(),
            'activated_by_user_id' => $this->activated_by_user_id,
            'created_at' => optional($this->created_at)?->toAtomString(),
        ];
    }
}
