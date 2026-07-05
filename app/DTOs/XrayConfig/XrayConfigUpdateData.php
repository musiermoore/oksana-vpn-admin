<?php

declare(strict_types=1);

namespace App\DTOs\XrayConfig;

use App\DTOs\Data;

class XrayConfigUpdateData extends Data
{
    public function __construct(
        public int $userId,
    ) {}
}
