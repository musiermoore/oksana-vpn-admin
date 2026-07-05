<?php

declare(strict_types=1);

namespace App\DTOs\XrayConfig;

use App\DTOs\Data;

class XrayConfigStoreData extends Data
{
    public function __construct(
        public string $protocol,
        public int $userId,
        public int $serverId,
        public int $inboundId,
    ) {}
}
