<?php

namespace App\DTOs\VlessConfig;

readonly class VlessConfigStoreData
{
    public function __construct(
        public int $userId,
        public int $configId,
    ) {}
}
