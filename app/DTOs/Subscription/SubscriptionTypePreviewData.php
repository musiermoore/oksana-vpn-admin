<?php

declare(strict_types=1);

namespace App\DTOs\Subscription;

use App\DTOs\Data;

final class SubscriptionTypePreviewData extends Data
{
    /** @param array<int, array{source_type:string,source_id:int,sort_order:int,is_free?:bool}> $items */
    public function __construct(
        public string $kind,
        public string $format,
        public bool $requiresUserToken,
        public bool $showExpiredPlaceholder,
        public array $items,
        public ?string $audience = null,
        public ?int $userId = null,
    ) {}
}
