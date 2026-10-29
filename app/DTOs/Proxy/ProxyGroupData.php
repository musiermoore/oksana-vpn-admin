<?php

declare(strict_types=1);

namespace App\DTOs\Proxy;

use App\DTOs\Data;

class ProxyGroupData extends Data
{
    public function __construct(public string $name) {}

    /** @return array{name:string} */
    public function toArray(): array
    {
        return ['name' => $this->name];
    }
}
