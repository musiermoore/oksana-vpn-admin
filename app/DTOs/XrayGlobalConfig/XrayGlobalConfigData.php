<?php

declare(strict_types=1);

namespace App\DTOs\XrayGlobalConfig;

use App\DTOs\Data;

class XrayGlobalConfigData extends Data
{
    /**
     * @param array<string, mixed> $dns
     * @param array<string, mixed> $routing
     * @param array<int, array<string, mixed>> $rules
     */
    public function __construct(
        public array $dns,
        public array $routing,
        public array $rules,
    ) {}
}
