<?php

namespace App\Services\Subscriptions;

use App\DTOs\Subscription\NormalizedNode;
use App\Models\ShadowsocksConfig;
use App\Models\User;
use App\Models\VlessConfig;
use Illuminate\Support\Facades\Http;

class NormalizedNodeService
{
    public function __construct(
        private readonly SubscriptionUriParser $parser,
    ) {}

    /**
     * @return array<int, NormalizedNode>
     */
    public function collect(User $user): array
    {
        $vlessConfigs = $user->vlessConfigs()
            ->where('vless_configs.is_active', true)
            ->where('vless_configs.enable', true)
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
                fn (array $item) => (string) $item['server_sort'],
                fn (array $item) => (int) $item['server_id'],
                fn (array $item) => $this->getTypeSortOrder((string) $item['type']),
                fn (array $item) => (int) $item['config_id'],
            ])
            ->values();

        $nodes = $items
            ->flatMap(fn (array $item) => $this->buildNodesForItem($item))
            ->unique(fn (NormalizedNode $node) => $node->uri)
            ->sortBy([
                fn (NormalizedNode $node) => $node->sortServerName,
                fn (NormalizedNode $node) => $node->serverId,
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

        if ($config instanceof VlessConfig) {
            return collect($this->getVlessUris($config))
                ->map(fn (string $uri, int $index) => $this->buildNode($uri, $item, $index))
                ->filter()
                ->values()
                ->all();
        }

        if ($config instanceof ShadowsocksConfig) {
            $node = $this->buildNode($config->getLink(), $item, 0);

            return $node ? [$node] : [];
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
}
