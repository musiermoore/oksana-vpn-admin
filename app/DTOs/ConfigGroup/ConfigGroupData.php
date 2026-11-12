<?php

declare(strict_types=1);

namespace App\DTOs\ConfigGroup;

use App\DTOs\Data;

final class ConfigGroupData extends Data
{
    public function __construct(
        public string $name,
        public bool $isDefault,
        public ?int $sort = null,
    ) {}

    /** @return array{name:string,sort:int,is_default:bool} */
    public function toModelAttributes(int $resolvedSort): array
    {
        return [
            'name' => $this->name,
            'sort' => $this->sort ?? $resolvedSort,
            'is_default' => $this->isDefault,
        ];
    }
}
