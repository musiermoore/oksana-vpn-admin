<?php

namespace App\DTOs\XrayConfig;

readonly class XrayConfigUpdateData
{
    public function __construct(
        public int $userId,
    ) {}
}
