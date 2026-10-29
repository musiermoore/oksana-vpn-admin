<?php

declare(strict_types=1);

namespace App\DTOs\Proxy;

use App\DTOs\Data;

class ProxyHostData extends Data
{
    public function __construct(
        public string $host,
        public int $port,
        public int $sort,
        public bool $isActive,
        public ?int $id = null,
    ) {}
}
