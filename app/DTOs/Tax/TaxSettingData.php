<?php

declare(strict_types=1);

namespace App\DTOs\Tax;

use App\DTOs\Data;

class TaxSettingData extends Data
{
    public function __construct(
        public string $serviceName,
        public ?string $login = null,
        public ?string $password = null,
    ) {}
}
