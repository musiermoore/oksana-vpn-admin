<?php

namespace App\DTOs\Subscription;

class SubscriptionBuildResult
{
    public function __construct(
        public readonly string $content,
        public readonly string $contentType,
        public readonly string $fileExtension,
    ) {}
}
