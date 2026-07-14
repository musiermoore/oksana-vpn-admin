<?php

namespace App\Services\Subscriptions;

use App\DTOs\Subscription\NormalizedNode;
use App\DTOs\Subscription\SubscriptionBuildResult;
use App\Models\User;
use App\Services\ExternalSubscriptions\VlessExternalSubscriptionAccessService;
use App\Services\ExternalSubscriptions\VlessExternalSubscriptionSyncService;

class UserSubscriptionService
{
    public function __construct(
        private readonly NormalizedNodeService $normalizedNodeService,
        private readonly NodeNameService $nodeNameService,
        private readonly SubscriptionBuilderFactory $builderFactory,
        private readonly VlessExternalSubscriptionAccessService $externalSubscriptions,
    ) {}

    public function build(User $user, ?string $format = null): SubscriptionBuildResult
    {
        $namedNodes = $this->buildNamedNodes($user);

        return $this->buildFromNodes($namedNodes, $format);
    }

    /**
     * @param  array<int, NormalizedNode>  $nodes
     */
    public function buildFromNodes(array $nodes, ?string $format = null): SubscriptionBuildResult
    {
        if ($nodes === []) {
            return $this->builderFactory
                ->make((string) $format)
                ->build([]);
        }

        return $this->builderFactory
            ->make((string) $format)
            ->build($nodes);
    }

    /**
     * @return array<int, array{
     *     url: string,
     *     config: array{
     *         id: int,
     *         name: string,
     *         domain: string,
     *         port: int,
     *         protocol: string,
     *         transport: string,
     *         inbound_id: int|null
     *     },
     *     server: array{id: int, name: string}
     * }>
     */
    public function buildDebug(User $user): array
    {
        return $this->buildDebugFromNodes($this->buildNamedNodes($user));
    }

    /**
     * @param  array<int, NormalizedNode>  $nodes
     * @return array<int, array{
     *     url: string,
     *     config: array{
     *         id: int,
     *         name: string,
     *         domain: string,
     *         port: int,
     *         protocol: string,
     *         transport: string,
     *         inbound_id: int|null
     *     },
     *     server: array{id: int, name: string}
     * }>
     */
    public function buildDebugFromNodes(array $nodes): array
    {
        return array_map(function (NormalizedNode $node): array {
            return [
                'url' => $node->uri,
                'config' => [
                    'id' => $node->configId,
                    'name' => (string) ($node->meta['name'] ?? $node->meta['config_name'] ?? $node->serverName),
                    'domain' => $this->extractDomain($node->uri),
                    'port' => (int) ($node->meta['config_port'] ?? 0),
                    'protocol' => (string) ($node->meta['config_protocol'] ?? $node->protocol),
                    'transport' => $node->transport,
                    'inbound_id' => isset($node->meta['config_inbound_id'])
                        ? (is_null($node->meta['config_inbound_id']) ? null : (int) $node->meta['config_inbound_id'])
                        : null,
                ],
                'server' => [
                    'id' => $node->serverId,
                    'name' => $node->serverName,
                ],
            ];
        }, $nodes);
    }

    /**
     * @return array<int, NormalizedNode>
     */
    public function getExpiredPlaceholderNodes(): array
    {
        return [$this->buildExpiredPlaceholderNode()];
    }

    /**
     * @return array<int, NormalizedNode>
     */
    private function buildNamedNodes(User $user): array
    {
        $nodes = [
            ...$this->normalizedNodeService->collect($user),
            ...$this->externalSubscriptions->getNamedNodesForUserByPurpose($user, VlessExternalSubscriptionSyncService::PURPOSE_MAIN),
        ];

        if (! $user->hasActiveAccess()) {
            array_unshift($nodes, $this->buildExpiredPlaceholderNode());
        }

        $names = $this->nodeNameService->buildNames($nodes);

        $namedNodes = array_map(
            fn (NormalizedNode $node) => new NormalizedNode(
                id: $node->id,
                serverName: $node->serverName,
                protocol: $node->protocol,
                transport: $node->transport,
                uri: $node->uri,
                serverId: $node->serverId,
                configId: $node->configId,
                sourceType: $node->sourceType,
                sortServerName: $node->sortServerName,
                meta: [...$node->meta, 'name' => $names[$node->id] ?? $node->serverName],
            ),
            $nodes
        );

        usort($namedNodes, function (NormalizedNode $left, NormalizedNode $right): int {
            $comparisons = [
                $left->serverId <=> $right->serverId,
                $left->sortServerName <=> $right->sortServerName,
                $this->getTypeSortOrder($left->protocol) <=> $this->getTypeSortOrder($right->protocol),
                $left->configId <=> $right->configId,
                mb_strtolower($left->transport) <=> mb_strtolower($right->transport),
                $left->uri <=> $right->uri,
            ];

            foreach ($comparisons as $comparison) {
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return 0;
        });

        return $namedNodes;
    }

    private function buildExpiredPlaceholderNode(): NormalizedNode
    {
        return new NormalizedNode(
            id: 'expired-placeholder',
            serverName: 'Ваша подписка закончилась 🚨',
            protocol: 'vless',
            transport: 'ws',
            uri: 'vless://00000000-0000-0000-0000-000000000000@expired.invalid:443?type=ws&security=tls&host=expired.invalid&path=%2F#expired',
            serverId: 0,
            configId: 0,
            sourceType: 'placeholder',
            sortServerName: 'vasha-podpiska-zakonchilas',
            meta: [
                'name' => 'Ваша подписка закончилась 🚨',
                'config_name' => 'Ваша подписка закончилась 🚨',
                'config_protocol' => 'vless',
                'config_port' => 443,
            ],
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

    private function extractDomain(string $uri): string
    {
        $host = parse_url($uri, PHP_URL_HOST);

        return is_string($host) ? $host : '';
    }
}
