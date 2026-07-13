<?php

namespace App\Services\Subscriptions;

use App\DTOs\Subscription\NormalizedNode;
use App\Models\Proxy;
use App\Models\Server;
use App\Models\ShadowsocksConfig;
use App\Models\User;
use App\Models\VlessConfig;
use Illuminate\Support\Collection;

class NormalizedNodeService
{
    public function __construct(
        private readonly SubscriptionUriParser $parser,
        private readonly SubscriptionUriTransformer $uriTransformer,
    ) {}

    /**
     * @return array<int, NormalizedNode>
     */
    public function collect(User $user): array
    {
        $vlessConfigs = $user->vlessConfigs()
            ->where('vless_configs.is_active', true)
            ->where('vless_configs.enable', true)
            ->whereHas('server', fn ($query) => $query->where('is_active', true))
            ->with('server')
            ->get()
            ->map(fn (VlessConfig $config) => [
                'type' => 'vless',
                'server_id' => (int) $config->server->getKey(),
                'server' => (string) $config->server->name,
                'server_sort' => mb_strtolower((string) $config->server->name),
                'config_id' => (int) $config->getKey(),
                'config' => $config,
            ]);

        $shadowsocksConfigs = $user->shadowsocksConfigs()
            ->where('shadowsocks_configs.is_active', true)
            ->where('shadowsocks_configs.enable', true)
            ->whereHas('server', fn ($query) => $query->where('is_active', true))
            ->with('server')
            ->get()
            ->map(fn (ShadowsocksConfig $config) => [
                'type' => 'shadowsocks',
                'server_id' => (int) $config->server->getKey(),
                'server' => (string) $config->server->name,
                'server_sort' => mb_strtolower((string) $config->server->name),
                'config_id' => (int) $config->getKey(),
                'config' => $config,
            ]);

        $items = $vlessConfigs
            ->concat($shadowsocksConfigs)
            ->sortBy([
                fn (array $item) => (int) $item['server_id'],
                fn (array $item) => (string) $item['server_sort'],
                fn (array $item) => $this->getTypeSortOrder((string) $item['type']),
                fn (array $item) => (int) $item['config_id'],
            ])
            ->values();

        $proxyIndex = $this->buildProxyIndex($items, $user);

        $nodes = $items
            ->flatMap(fn (array $item) => $this->buildNodesForItem($item, $proxyIndex))
            ->unique(fn (NormalizedNode $node) => $node->uri)
            ->sortBy([
                fn (NormalizedNode $node) => $node->serverId,
                fn (NormalizedNode $node) => $node->sortServerName,
                fn (NormalizedNode $node) => $this->getTypeSortOrder($node->protocol),
                fn (NormalizedNode $node) => $node->configId,
                fn (NormalizedNode $node) => mb_strtolower($node->transport),
                fn (NormalizedNode $node) => $node->uri,
            ])
            ->values();

        return $nodes->all();
    }

    /**
     * @param  array{type:string, server_id:int, server:string, server_sort:string, config_id:int, config:VlessConfig|ShadowsocksConfig}  $item
     * @param  array<string, Collection<int, Proxy>>  $proxyIndex
     * @return array<int, NormalizedNode>
     */
    private function buildNodesForItem(array $item, array $proxyIndex): array
    {
        $config = $item['config'];

        if ($config instanceof VlessConfig) {
            $proxies = $this->resolveReadyProxies(
                serverId: (int) $config->server_id,
                inboundId: $config->inbound_id ?? null,
                proxyIndex: $proxyIndex,
                requireExactInboundMatch: true,
            );
            $directNodes = collect($this->getVlessUris($config))
                ->map(fn (string $uri, int $index) => $this->buildNode($uri, $item, $index))
                ->filter()
                ->values();

            if ($proxies->isEmpty()) {
                return $directNodes->all();
            }

            $proxyNodes = $directNodes
                ->flatMap(fn (NormalizedNode $node, int $index) => $proxies
                    ->map(fn (Proxy $proxy) => $this->buildProxyNode($node, $proxy, $index)))
                ->filter()
                ->values();

            return $directNodes
                ->concat($proxyNodes)
                ->values()
                ->all();
        }

        if ($config instanceof ShadowsocksConfig) {
            $proxies = $this->resolveReadyProxies(
                serverId: (int) $config->server_id,
                inboundId: $config->inbound_id ?? null,
                proxyIndex: $proxyIndex,
            );
            $node = $this->buildNode($config->getLink(), $item, 0);

            if (! $node) {
                return [];
            }

            if ($proxies->isEmpty()) {
                return [$node];
            }

            $proxyNodes = $proxies
                ->map(fn (Proxy $proxy) => $this->buildProxyNode($node, $proxy, 0))
                ->filter()
                ->values()
                ->all();

            return [
                $node,
                ...$proxyNodes,
            ];
        }

        return [];
    }

    /**
     * @param  array{type:string, server_id:int, server:string, server_sort:string, config_id:int, config:VlessConfig|ShadowsocksConfig}  $item
     */
    private function buildNode(string $uri, array $item, int $index): ?NormalizedNode
    {
        if (trim($uri) === '') {
            return null;
        }

        $parsed = $this->parser->parse($uri);

        if (! is_array($parsed)) {
            return null;
        }

        $config = $item['config'];

        return new NormalizedNode(
            id: implode(':', [$item['server_id'], $item['config_id'], $index, md5($uri)]),
            serverName: $item['server'],
            protocol: (string) $parsed['protocol'],
            transport: $this->parser->detectTransport($uri),
            uri: $uri,
            serverId: $item['server_id'],
            configId: $item['config_id'],
            sourceType: $item['type'],
            sortServerName: $item['server_sort'],
            meta: [
                'config_name' => (string) $config->name,
                'config_port' => (int) $config->port,
                'config_protocol' => $config instanceof VlessConfig
                    ? (string) ($config->protocol ?: 'vless')
                    : 'shadowsocks',
                'config_inbound_id' => $config->inbound_id === null ? null : (int) $config->inbound_id,
            ],
        );
    }

    private function buildProxyNode(NormalizedNode $node, Proxy $proxy, int $index): ?NormalizedNode
    {
        $proxyUri = $this->uriTransformer->replaceAddress($node->uri, (string) $proxy->host, (int) $proxy->port);

        if (! is_string($proxyUri) || $proxyUri === '') {
            return null;
        }

        $serverName = sprintf('%s (%s)', $node->serverName, $proxy->name);

        return new NormalizedNode(
            id: $node->id.':proxy:'.$proxy->id.':'.$index,
            serverName: $serverName,
            protocol: $node->protocol,
            transport: $node->transport,
            uri: $proxyUri,
            serverId: $node->serverId,
            configId: $node->configId,
            sourceType: $node->sourceType,
            sortServerName: mb_strtolower($serverName),
            meta: [
                ...$node->meta,
                'proxy_id' => (int) $proxy->id,
                'proxy_name' => (string) $proxy->name,
            ],
        );
    }

    /**
     * @return array<int, string>
     */
    private function getVlessUris(VlessConfig $config): array
    {
        return [$config->getStaticLink()];
    }

    private function isSupportedSubscriptionLink(string $line): bool
    {
        return in_array(
            $this->parser->detectProtocol($line),
            ['vless', 'trojan', 'shadowsocks', 'hysteria', 'hysteria2'],
            true
        );
    }

    private function getTypeSortOrder(string $type): int
    {
        return match ($type) {
            'vless' => 0,
            'trojan' => 1,
            'shadowsocks' => 2,
            'hysteria' => 3,
            'hysteria2' => 4,
            'wireguard' => 5,
            default => 99,
        };
    }

    /**
     * @return Collection<int, Proxy>
     */
    private function resolveReadyProxies(
        int $serverId,
        ?int $inboundId = null,
        array $proxyIndex = [],
        bool $requireExactInboundMatch = false,
    ): Collection
    {
        $exactProxies = $proxyIndex[$this->proxyIndexKey($serverId, $inboundId)] ?? collect();

        if ($exactProxies->isNotEmpty()) {
            return $exactProxies;
        }

        if ($requireExactInboundMatch) {
            return collect();
        }

        return $proxyIndex[$this->proxyIndexKey($serverId, null)] ?? collect();
    }

    /**
     * @param  Collection<int, array{type:string, server_id:int, server:string, server_sort:string, config_id:int, config:VlessConfig|ShadowsocksConfig}>  $items
     * @return array<string, Collection<int, Proxy>>
     */
    private function buildProxyIndex(Collection $items, User $user): array
    {
        $serverIds = $items
            ->pluck('server_id')
            ->map(fn (mixed $serverId) => (int) $serverId)
            ->unique()
            ->values()
            ->all();

        if ($serverIds === []) {
            return [];
        }

        $proxyIndex = [];

        $proxyQuery = Proxy::query()
            ->whereHas('servers', fn ($query) => $query->whereIn('servers.id', $serverIds))
            ->with([
                'servers' => fn ($query) => $query
                    ->whereIn('servers.id', $serverIds)
                    ->select('servers.id'),
            ])
            ->orderBy('id');

        if (! $user->is_admin) {
            $proxyQuery->where('is_ready', true);
        }

        $proxyQuery
            ->get()
            ->each(function (Proxy $proxy) use (&$proxyIndex): void {
                $proxy->servers->each(function (Server $server) use (&$proxyIndex, $proxy): void {
                    $key = $this->proxyIndexKey((int) $server->getKey(), $proxy->inbound_id);

                    if (! array_key_exists($key, $proxyIndex)) {
                        $proxyIndex[$key] = collect();
                    }

                    $proxyIndex[$key]->push($proxy);
                });
            });

        return $proxyIndex;
    }

    private function proxyIndexKey(int $serverId, ?int $inboundId): string
    {
        return $serverId.':'.($inboundId === null ? 'null' : $inboundId);
    }
}
