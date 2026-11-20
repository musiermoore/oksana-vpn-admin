<?php

declare(strict_types=1);

namespace App\DTOs\PromoCode;

use App\DTOs\Data;

class PreviewPromoCodeData extends Data
{
    public function __construct(public int $month, public string $promoCode) {}
}
