<?php

namespace App\DTOs\XrayConfig;

readonly class XrayConfigStoreData
{
    public function __construct(
        public string $protocol,
        public int $userId,
        public int $serverId,
        public int $inboundId,
    ) {}
}
