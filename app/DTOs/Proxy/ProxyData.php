<?php

declare(strict_types=1);

namespace App\DTOs\Proxy;

use App\DTOs\Data;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\DataCollection;

class ProxyData extends Data
{
    public function __construct(
        public string $name,
        /** @var DataCollection<int, ProxyHostData> */
        #[DataCollectionOf(ProxyHostData::class)]
        public DataCollection $hosts,
        public bool $isHttps,
        public bool $isReady,
        public bool $hideMainNodeName,
        public int $serverId,
        public ?int $inboundId = null,
        public ?string $description = null,
        public ?int $proxyGroupId = null,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'server_id' => $this->serverId,
            'proxy_group_id' => $this->proxyGroupId,
            'is_https' => $this->isHttps,
            'is_ready' => $this->isReady,
            'hide_main_node_name' => $this->hideMainNodeName,
            'description' => $this->description,
            'hosts' => $this->hosts->toArray(),
        ];
    }
}
