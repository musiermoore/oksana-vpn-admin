<?php

declare(strict_types=1);

namespace App\DTOs\PromoCode;

use App\DTOs\Data;
use App\Enums\PromoCodeBenefit;

class PromoCodeData extends Data
{
    public function __construct(
        public string $code,
        public PromoCodeBenefit $benefit,
        public float $value,
        public bool $oncePerUser = true,
        public bool $newUsersOnly = false,
        public bool $isActive = true,
        public ?string $startsOn = null,
        public ?string $endsOn = null,
    ) {}
}
