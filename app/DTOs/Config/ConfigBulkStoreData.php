<?php

declare(strict_types=1);

namespace App\DTOs\Config;

use App\DTOs\Data;

class ConfigBulkStoreData extends Data
{
    public function __construct(
        public int $serverId,
    ) {}
}
