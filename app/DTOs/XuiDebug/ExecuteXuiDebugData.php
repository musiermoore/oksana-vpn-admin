<?php

declare(strict_types=1);

namespace App\DTOs\XuiDebug;

use App\DTOs\Data;

class ExecuteXuiDebugData extends Data
{
    public function __construct(
        public int $serverId,
        public string $preset,
        public string $method,
        public string $endpoint,
        public string $encoding,
        public ?string $payload = null,
    ) {}
}
