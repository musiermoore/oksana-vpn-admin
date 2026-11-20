<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PromoCodeRedemption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PromoCodeRedemption */
class PromoCodeRedemptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->full_name,
            'user_url' => $this->user && ! $this->user->trashed() ? route('users.edit', $this->user, false) : null,
            'code' => $this->code,
            'benefit' => $this->benefit->value,
            'benefit_label' => $this->benefit->label(),
            'value' => $this->value,
            'discount_amount' => $this->discount_amount,
            'payment_transaction_id' => $this->payment_transaction_id,
            'subscription_transaction_id' => $this->subscription_transaction_id,
            'status' => match (true) {
                $this->redeemed_at !== null => 'Использован',
                $this->canceled_at !== null, $this->payment_transaction_id === null => 'Отменён',
                default => 'Ожидает оплаты',
            },
            'created_at' => $this->created_at->format('d.m.Y H:i'),
            'redeemed_at' => $this->redeemed_at?->format('d.m.Y H:i'),
        ];
    }
}
