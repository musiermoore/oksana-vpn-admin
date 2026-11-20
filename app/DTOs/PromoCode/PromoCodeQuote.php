<?php

declare(strict_types=1);

namespace App\DTOs\PromoCode;

use App\Enums\PromoCodeBenefit;
use App\Models\PromoCode;

final readonly class PromoCodeQuote
{
    public float $discountAmount;

    public float $packagePrice;

    public float $payableNow;

    public function __construct(public PromoCode $promoCode, float $packagePrice, float $balance)
    {
        $this->discountAmount = $promoCode->benefit->discount($packagePrice, $promoCode->value);
        $this->packagePrice = round($packagePrice - $this->discountAmount, 2);
        $deposit = $promoCode->benefit === PromoCodeBenefit::BalanceDeposit ? $promoCode->value : 0;
        $this->payableNow = round(max(0, $this->packagePrice - $balance - $deposit), 2);
    }
}
