<?php

declare(strict_types=1);

namespace App\DTOs\VlessExternalSubscription;

use App\DTOs\Data;

class VlessExternalSubscriptionData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $type,
        public string $sourceFormat,
        public string $sourceUrl,
        public ?string $filterPattern,
        public ?string $connectNamePrefix,
        public bool $includeInMainSubscription,
        public bool $includeInWhitelist,
        public bool $isFree,
        public bool $isActive,
        public bool $isReady,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toModelAttributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'source_format' => $this->sourceFormat,
            'source_url' => $this->sourceUrl,
            'filter_pattern' => $this->filterPattern,
            'connect_name_prefix' => $this->connectNamePrefix,
            'include_in_main_subscription' => $this->includeInMainSubscription,
            'include_in_whitelist' => $this->includeInWhitelist,
            'is_free' => $this->isFree,
            'is_active' => $this->isActive,
            'is_ready' => $this->isReady,
        ];
    }
}
