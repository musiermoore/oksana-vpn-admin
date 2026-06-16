<?php

namespace App\DTOs\Message;

readonly class WelcomeMessagesData
{
    public function __construct(
        public string $basicText,
        public string $extendedText,
    ) {}
}
