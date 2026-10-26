<?php

declare(strict_types=1);

namespace App\DTOs\ServerConsole;

use App\DTOs\Data;

class ServerConsoleInputData extends Data
{
    public function __construct(public string $input) {}
}
