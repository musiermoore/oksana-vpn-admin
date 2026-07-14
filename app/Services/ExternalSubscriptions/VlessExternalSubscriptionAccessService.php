<?php

namespace App\Services\ExternalSubscriptions;

use App\DTOs\Subscription\NormalizedNode;
use App\Models\User;
use App\Models\VlessExternalSubscriptionConfig;
use App\Services\Subscriptions\NodeNameService;
use App\Services\Subscriptions\SubscriptionUriParser;

class VlessExternalSubscriptionAccessService
{
    public function __construct(
        private readonly VlessExternalSubscriptionSyncService $syncService,
        private readonly SubscriptionUriParser $parser,
        private readonly NodeNameService $nodeNameService,
    ) {}

    /**
     * @return array<int, NormalizedNode>
     */
    public function getNamedNodesForUser(User $user): array
    {
        return $this->getNamedNodesForUserByPurpose($user, VlessExternalSubscriptionSyncService::PURPOSE_WHITELIST);
    }

    /**
     * @return array<int, NormalizedNode>
     */
    public function getNamedNodesForUserByPurpose(User $user, string $purpose): array
    {
        $configs = collect($this->syncService->getVisibleConfigsForUser($user, $purpose))
            ->values();

        $nodes = $configs
            ->map(fn (VlessExternalSubscriptionConfig $config, int $index) => $this->mapConfigToNode($config, $index))
            ->filter()
            ->values()
            ->all();

        $names = $this->nodeNameService->buildNames($nodes);
        $customNames = $this->buildCustomNames($configs);

        return array_map(
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
                meta: [...$node->meta, 'name' => $customNames[$node->id] ?? $names[$node->id] ?? $node->serverName],
            ),
            $nodes
        );
    }

    /**
     * @return array<int, array{
     *     url: string,
     *     config: array{id:int, name:string, domain:string, port:int, protocol:string, transport:string, inbound_id:null},
     *     server: array{id:int, name:string}
     * }>
     */
    public function buildDebug(User $user): array
    {
        return array_map(function (NormalizedNode $node): array {
            return [
                'url' => $node->uri,
                'config' => [
                    'id' => $node->configId,
                    'name' => (string) ($node->meta['name'] ?? $node->serverName),
                    'domain' => (string) ($node->meta['server'] ?? ''),
                    'port' => (int) ($node->meta['port'] ?? 0),
                    'protocol' => (string) ($node->meta['config_protocol'] ?? $node->protocol),
                    'transport' => $node->transport,
                    'inbound_id' => null,
                ],
                'server' => [
                    'id' => $node->serverId,
                    'name' => $node->serverName,
                ],
            ];
        }, $this->getNamedNodesForUser($user));
    }

    private function mapConfigToNode(VlessExternalSubscriptionConfig $config, int $index): ?NormalizedNode
    {
        $parsed = $this->parser->parse($config->url);

        if (! is_array($parsed)) {
            return null;
        }

        $serverName = (string) $config->subscription->name;

        return new NormalizedNode(
            id: 'wl:'.$config->id,
            serverName: $serverName,
            protocol: (string) ($parsed['protocol'] ?? 'unknown'),
            transport: $this->parser->detectTransport($config->url),
            uri: $config->url,
            serverId: 900000 + (int) $config->subscription->id,
            configId: (int) $config->id + $index,
            sourceType: 'external_subscription',
            sortServerName: mb_strtolower($serverName),
            meta: [
                'config_name' => (string) $config->name,
                'config_protocol' => (string) ($config->protocol ?: ($parsed['protocol'] ?? 'unknown')),
                'server' => (string) ($parsed['server'] ?? ''),
                'port' => (int) ($parsed['port'] ?? 0),
            ],
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, VlessExternalSubscriptionConfig>  $configs
     * @return array<string, string>
     */
    private function buildCustomNames(\Illuminate\Support\Collection $configs): array
    {
        $names = [];

        $configs
            ->groupBy(fn (VlessExternalSubscriptionConfig $config) => (int) $config->vless_external_subscription_id)
            ->each(function (\Illuminate\Support\Collection $group) use (&$names): void {
                $prefix = trim((string) $group->first()?->subscription?->connect_name_prefix);

                if ($prefix === '') {
                    return;
                }

                if ($group->count() === 1) {
                    $config = $group->first();

                    if ($config !== null) {
                        $names['wl:'.$config->id] = $prefix;
                    }

                    return;
                }

                $group->values()->each(function (VlessExternalSubscriptionConfig $config, int $index) use (&$names, $prefix): void {
                    $names['wl:'.$config->id] = sprintf('%s %d', $prefix, $index + 1);
                });
            });

        return $names;
    }
}
