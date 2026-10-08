<?php

declare(strict_types=1);

namespace App\DTOs\XrayCustomConfig;

use App\DTOs\Data;

class XrayDnsSettingsData extends Data
{
    /**
     * @param  array<int, string|array<string, mixed>>  $servers
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public array $servers,
        public string $queryStrategy,
        public bool $enableParallelQuery = false,
        public bool $isDefault = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toModelAttributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'servers' => $this->servers,
            'query_strategy' => $this->queryStrategy,
            'enable_parallel_query' => $this->enableParallelQuery,
            'is_default' => $this->isDefault,
        ];
    }
}
