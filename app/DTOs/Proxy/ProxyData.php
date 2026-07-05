<?php

declare(strict_types=1);

namespace App\DTOs\Proxy;

use App\DTOs\Data;

class ProxyData extends Data
{
    /**
     * @param  array<int, int>  $serverIds
     */
    public function __construct(
        public string $name,
        public string $host,
        public int $port,
        public bool $isHttps,
        public bool $isReady,
        public ?int $inboundId = null,
        public ?string $description = null,
        public array $serverIds = [],
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'host' => $this->host,
            'port' => $this->port,
            'inbound_id' => $this->inboundId,
            'is_https' => $this->isHttps,
            'is_ready' => $this->isReady,
            'description' => $this->description,
        ];
    }
}
