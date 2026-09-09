<?php

declare(strict_types=1);

namespace App\DTOs\XrayRouting;

use App\DTOs\Data;

class XrayRoutingImportData extends Data
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
    ) {}
}
