<?php

declare(strict_types=1);

namespace App\DTOs\Proxy;

use App\DTOs\Data;

class ProxyData extends Data
{
    /**
     */
    public function __construct(
        public string $name,
        public string $host,
        public int $port,
        public bool $isHttps,
        public bool $isReady,
        public bool $hideMainNodeName,
        public int $serverId,
        public ?int $inboundId = null,
        public ?string $description = null,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'host' => $this->host,
            'port' => $this->port,
            'server_id' => $this->serverId,
            'is_https' => $this->isHttps,
            'is_ready' => $this->isReady,
            'hide_main_node_name' => $this->hideMainNodeName,
            'description' => $this->description,
        ];
    }
}
