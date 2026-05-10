<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'price' => (float) $this->price,
            'is_active' => now()->betweenIncluded($this->start_date, $this->end_date),
            'user' => $this->user ? [
                'id' => $this->user->id,
                'full_name' => $this->user->full_name,
                'telegram' => $this->user->telegram,
                'is_active' => $this->user->is_active,
                'edit_url' => route('users.edit', $this->user),
            ] : null,
            'links' => [
                'edit' => route('subscriptions.edit', $this->resource),
            ],
        ];
    }
}
