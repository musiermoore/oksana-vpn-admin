<?php

declare(strict_types=1);

namespace App\DTOs\Tax;

use App\DTOs\Data;

class TaxDebugRequestData extends Data
{
    public function __construct(
        public string $preset,
        public ?int $invoiceId = null,
        public ?int $userId = null,
    ) {}
}
