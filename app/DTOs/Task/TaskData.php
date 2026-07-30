<?php

declare(strict_types=1);

namespace App\DTOs\Task;

use App\DTOs\Data;

class TaskData extends Data
{
    public function __construct(
        public string $title,
        public string $status,
        public ?string $description = null,
        public ?string $dueDate = null,
    ) {}
}
