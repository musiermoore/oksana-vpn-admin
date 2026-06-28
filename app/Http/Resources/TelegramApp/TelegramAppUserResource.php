<?php

namespace App\Http\Resources\TelegramApp;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TelegramAppUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'telegram' => $this->telegram,
            'telegram_id' => $this->telegram_id,
            'balance' => (float) ($this->balance ?? 0),
            'debt' => max(0, -(float) ($this->balance ?? 0)),
            'is_admin' => (bool) $this->is_admin,
            'has_active_access' => $this->hasActiveAccess(),
            'subscription_expires_at' => optional($this->subscription_expires_at)?->toAtomString(),
            'has_money_for_next_subscription_month' => $this->when(
                isset($this->has_money_for_next_subscription_month),
                fn () => (bool) $this->has_money_for_next_subscription_month,
            ),
            'referral' => $this->when(
                isset($this->referral_summary),
                fn () => $this->referral_summary
            ),
        ];
    }
}
