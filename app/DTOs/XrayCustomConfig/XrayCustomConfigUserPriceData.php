<?php

declare(strict_types=1);

namespace App\DTOs\XrayCustomConfig;

use App\DTOs\Data;

class XrayCustomConfigUserPriceData extends Data
{
    public function __construct(
        public int $xrayCustomConfigId,
        public float $price,
        public string $effectiveFrom,
    ) {}
}
