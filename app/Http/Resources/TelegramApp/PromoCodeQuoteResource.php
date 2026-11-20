<?php

declare(strict_types=1);

namespace App\Http\Resources\TelegramApp;

use App\DTOs\PromoCode\PromoCodeQuote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PromoCodeQuote */
class PromoCodeQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->promoCode->code,
            'benefit' => $this->promoCode->benefit->value,
            'value' => $this->promoCode->value,
            'discount_amount' => $this->discountAmount,
            'price' => $this->packagePrice,
            'payable_now' => $this->payableNow,
        ];
    }
}
