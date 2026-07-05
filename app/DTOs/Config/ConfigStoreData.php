<?php

declare(strict_types=1);

namespace App\DTOs\Config;

use App\DTOs\Data;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class ConfigStoreData extends Data
{
    /**
     * @param  array<int, ConfigCreateItemData>  $configs
     */
    public function __construct(
        public int $userId,
        #[DataCollectionOf(ConfigCreateItemData::class)]
        public array $configs,
    ) {}
}
