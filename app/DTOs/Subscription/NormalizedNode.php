<?php

namespace App\DTOs\Subscription;

class NormalizedNode
{
    /**
     * @param  array<string, scalar|array|null>  $meta
     */
    public function __construct(
        public readonly string $id,
        public readonly string $serverName,
        public readonly string $protocol,
        public readonly string $transport,
        public readonly string $uri,
        public readonly int $serverId,
        public readonly int $configId,
        public readonly string $sourceType,
        public readonly string $sortServerName,
        public readonly array $meta = [],
    ) {}
}
