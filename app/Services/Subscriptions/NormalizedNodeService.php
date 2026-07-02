<?php

namespace App\Services\Subscriptions;

use App\DTOs\Subscription\NormalizedNode;
use App\Models\Proxy;
use App\Models\ShadowsocksConfig;
use App\Models\User;
use App\Models\VlessConfig;
use Illuminate\Support\Facades\Http;

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
            ->with('server.proxies')
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
            ->with('server.proxies')
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

        $nodes = $items
            ->flatMap(fn (array $item) => $this->buildNodesForItem($item))
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
     * @return array<int, NormalizedNode>
     */
    private function buildNodesForItem(array $item): array
    {
        $config = $item['config'];
        $proxy = $this->resolveReadyProxy($config->server, $config->inbound_id ?? null);

        if ($config instanceof VlessConfig) {
            $directNodes = collect($this->getVlessUris($config))
                ->map(fn (string $uri, int $index) => $this->buildNode($uri, $item, $index))
                ->filter()
                ->values();

            if (! $proxy instanceof Proxy) {
                return $directNodes->all();
            }

            $proxyNodes = $directNodes
                ->map(fn (NormalizedNode $node, int $index) => $this->buildProxyNode($node, $proxy, $index))
                ->filter()
                ->values();

            return $directNodes
                ->concat($proxyNodes)
                ->values()
                ->all();
        }

        if ($config instanceof ShadowsocksConfig) {
            $node = $this->buildNode($config->getLink(), $item, 0);

            if (! $node) {
                return [];
            }

            if (! $proxy instanceof Proxy) {
                return [$node];
            }

            $proxyNode = $this->buildProxyNode($node, $proxy, 0);

            return array_values(array_filter([$node, $proxyNode]));
        }

        return [];
    }

    /**
     * @param  array{type:string, server_id:int, server:string, server_sort:string, config_id:int}  $item
     */
    private function buildNode(string $uri, array $item, int $index): ?NormalizedNode
    {
        $parsed = $this->parser->parse($uri);

        if (! is_array($parsed)) {
            return null;
        }

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
            meta: [],
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
        if (empty($config->sub_id)) {
            return [$config->getStaticLink()];
        }

        try {
            $response = Http::timeout(10)
                ->get($config->getSubscriptionLink())
                ->body();

            $decoded = base64_decode($response, true);

            if ($decoded === false) {
                $decoded = $response;
            }

            return collect(preg_split('/\r\n|\r|\n/', $decoded))
                ->map(fn ($line) => trim((string) $line))
                ->filter(fn ($line) => $line !== '' && $this->isSupportedSubscriptionLink($line))
                ->values()
                ->all();
        } catch (\Exception $exception) {
            report($exception);

            return [];
        }
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
            default => 99,
        };
    }

    private function resolveReadyProxy(\App\Models\Server $server, ?int $inboundId = null): ?Proxy
    {
        if ($server->relationLoaded('proxies')) {
            return $server->proxies
                ->filter(function (Proxy $proxy) use ($inboundId) {
                    if (! $proxy->is_ready) {
                        return false;
                    }

                    return $proxy->inbound_id === null || (int) $proxy->inbound_id === (int) $inboundId;
                })
                ->sortBy([
                    fn (Proxy $proxy) => $proxy->inbound_id === null ? 1 : 0,
                    fn (Proxy $proxy) => (int) $proxy->id,
                ])
                ->first();
        }

        return $server->proxies()
            ->where('proxies.is_ready', true)
            ->where(function ($query) use ($inboundId) {
                $query->whereNull('proxies.inbound_id');

                if ($inboundId !== null) {
                    $query->orWhere('proxies.inbound_id', $inboundId);
                }
            })
            ->orderByRaw('CASE WHEN proxies.inbound_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('proxies.id')
            ->first();
    }
}
