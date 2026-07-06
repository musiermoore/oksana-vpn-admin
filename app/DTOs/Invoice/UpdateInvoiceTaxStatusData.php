<?php

declare(strict_types=1);

namespace App\DTOs\Invoice;

use App\DTOs\Data;

class UpdateInvoiceTaxStatusData extends Data
{
    public function __construct(
        public string $taxStatus,
    ) {}
}
