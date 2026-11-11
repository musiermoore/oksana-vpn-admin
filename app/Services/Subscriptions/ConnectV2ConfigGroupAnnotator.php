<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\DTOs\Subscription\NormalizedNode;
use App\Models\ConfigGroup;
use App\Models\VlessConfig;
use App\Models\VlessExternalSubscription;

final class ConnectV2ConfigGroupAnnotator
{
    /**
     * @param array<int, NormalizedNode> $nodes
     * @return array<int, NormalizedNode>
     */
    public static function annotate(array $nodes): array
    {
        $localIds = collect($nodes)
            ->filter(fn (NormalizedNode $node): bool => $node->sourceType !== 'external_subscription' && $node->configId > 0)
            ->map(fn (NormalizedNode $node): int => $node->configId)
            ->unique()
            ->values();
        $subscriptionIds = collect($nodes)
            ->filter(fn (NormalizedNode $node): bool => $node->sourceType === 'external_subscription')
            ->map(fn (NormalizedNode $node): int => (int) ($node->meta['external_subscription_id'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $localGroups = VlessConfig::query()
            ->whereKey($localIds)
            ->with('configGroups:id,name')
            ->get()
            ->mapWithKeys(fn (VlessConfig $config): array => [
                (int) $config->id => self::groupReference($config->configGroups->first()),
            ]);
        $subscriptionGroups = VlessExternalSubscription::query()
            ->whereKey($subscriptionIds)
            ->with('configGroups:id,name')
            ->get()
            ->mapWithKeys(fn (VlessExternalSubscription $subscription): array => [
                (int) $subscription->id => self::groupReference($subscription->configGroups->first()),
            ]);

        return array_map(function (NormalizedNode $node) use ($localGroups, $subscriptionGroups): NormalizedNode {
            $group = $node->sourceType === 'external_subscription'
                ? $subscriptionGroups->get((int) ($node->meta['external_subscription_id'] ?? 0))
                : $localGroups->get($node->configId);

            return new NormalizedNode(
                id: $node->id,
                serverName: $node->serverName,
                sortGroupOrder: $node->sortGroupOrder,
                sortItemOrder: $node->sortItemOrder,
                protocol: $node->protocol,
                transport: $node->transport,
                uri: $node->uri,
                serverId: $node->serverId,
                configId: $node->configId,
                sourceType: $node->sourceType,
                sortServerName: $node->sortServerName,
                meta: [...$node->meta, 'config_group' => $group],
            );
        }, $nodes);
    }

    /** @return array{id:int,name:string}|null */
    private static function groupReference(?ConfigGroup $group): ?array
    {
        return $group === null ? null : ['id' => (int) $group->id, 'name' => (string) $group->name];
    }
}
