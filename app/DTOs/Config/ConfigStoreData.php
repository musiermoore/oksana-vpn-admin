<?php

namespace App\DTOs\Config;

readonly class ConfigStoreData
{
    /**
     * @param  array<int, ConfigCreateItemData>  $configs
     */
    public function __construct(
        public int $userId,
        public array $configs,
    ) {}
}
