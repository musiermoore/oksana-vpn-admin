<?php

declare(strict_types=1);

namespace App\DTOs\XrayCustomConfig;

use App\DTOs\Data;

class UpdateXrayCustomConfigUserPriceData extends Data
{
    public function __construct(
        public float $price,
        public string $effectiveFrom,
    ) {}
}
