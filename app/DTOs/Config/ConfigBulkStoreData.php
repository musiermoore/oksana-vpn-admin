<?php

namespace App\DTOs\Config;

readonly class ConfigBulkStoreData
{
    public function __construct(
        public int $serverId,
    ) {}
}
