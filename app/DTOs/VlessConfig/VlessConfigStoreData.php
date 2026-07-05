<?php

declare(strict_types=1);

namespace App\DTOs\VlessConfig;

use App\DTOs\Data;

class VlessConfigStoreData extends Data
{
    public function __construct(
        public int $userId,
        public int $serverId,
        public int $inboundId,
    ) {}
}
