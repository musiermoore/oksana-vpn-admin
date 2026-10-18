<?php

declare(strict_types=1);

namespace App\DTOs\HealthCheck;

use App\DTOs\Data;

final class UpdateInboundHealthCheckSettingsData extends Data
{
    public function __construct(public bool $is_enabled) {}
}
