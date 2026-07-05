<?php

declare(strict_types=1);

namespace App\DTOs\Message;

use App\DTOs\Data;

class WelcomeMessagesData extends Data
{
    public function __construct(
        public string $basicText = '',
        public string $extendedText = '',
    ) {}
}
