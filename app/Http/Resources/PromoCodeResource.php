<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PromoCode */
class PromoCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'benefit' => $this->benefit->value,
            'benefit_label' => $this->benefit->label(),
            'value' => $this->value,
            'once_per_user' => $this->once_per_user,
            'new_users_only' => $this->new_users_only,
            'is_active' => $this->is_active,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'is_archived' => $this->trashed(),
            'redeemed_count' => $this->redeemed_count,
            'links' => [
                'show' => route('promo-codes.show', $this->resource),
                'edit' => route('promo-codes.edit', $this->resource),
                'destroy' => route('promo-codes.destroy', $this->resource),
            ],
        ];
    }
}
