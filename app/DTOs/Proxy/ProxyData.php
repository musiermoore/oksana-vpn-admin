<?php

namespace App\DTOs\Proxy;

readonly class ProxyData
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
        public ?string $description,
        public array $serverIds,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'host' => $this->host,
            'port' => $this->port,
            'is_https' => $this->isHttps,
            'is_ready' => $this->isReady,
            'description' => $this->description,
        ];
    }
}
