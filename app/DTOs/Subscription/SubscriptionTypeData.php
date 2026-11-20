<?php

declare(strict_types=1);

namespace App\DTOs\Subscription;

use App\DTOs\Data;

final class SubscriptionTypeData extends Data
{
    /**
     * @param array<int, array{source_type:string,source_id:int,sort_order:int,is_free?:bool}> $items
     */
    public function __construct(
        public string $name,
        public string $slug,
        public string $kind,
        public string $format,
        public bool $isActive,
        public bool $requiresUserToken,
        public bool $showExpiredPlaceholder,
        public array $items,
    ) {}

    /** @return array{name:string,slug:string,kind:string,format:string,is_active:bool,requires_user_token:bool,show_expired_placeholder:bool} */
    public function toModelAttributes(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'kind' => $this->kind,
            'format' => $this->format,
            'is_active' => $this->isActive,
            'requires_user_token' => $this->requiresUserToken,
            'show_expired_placeholder' => $this->showExpiredPlaceholder,
        ];
    }
}
