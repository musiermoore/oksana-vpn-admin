<?php

declare(strict_types=1);

namespace App\DTOs\Reports;

use App\DTOs\Data;

class ReportsFilterData extends Data
{
    public function __construct(
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
    ) {}
}
