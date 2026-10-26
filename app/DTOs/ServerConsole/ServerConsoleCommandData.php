<?php

declare(strict_types=1);

namespace App\DTOs\ServerConsole;

use App\DTOs\Data;

class ServerConsoleCommandData extends Data
{
    public function __construct(
        public ?string $command = null,
        public ?int $templateId = null,
    ) {}
}
