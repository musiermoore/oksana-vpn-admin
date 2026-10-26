<?php

declare(strict_types=1);

namespace App\DTOs\ServerConsole;

use App\DTOs\Data;

class ServerConsoleResizeData extends Data
{
    public function __construct(public int $columns, public int $rows) {}
}
