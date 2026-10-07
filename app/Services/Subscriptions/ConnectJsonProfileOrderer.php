<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\DTOs\Subscription\NormalizedNode;
use App\DTOs\Subscription\SubscriptionBuildResult;
use App\Models\XrayCustomConfig;
use App\Models\XrayRouting;
use App\Services\Subscriptions\Builders\ConnectJsonBuilder;

final class ConnectJsonProfileOrderer
{
    /**
     * @param  array<int, NormalizedNode>  $nodes
     * @param  array<int, NormalizedNode>  $customNodes
     */
    public function build(
        ConnectJsonBuilder $builder,
        array $nodes,
        array $customNodes,
    ): SubscriptionBuildResult {
        $customConfigs = XrayCustomConfig::query()
            ->active()
            ->ordered()
            ->with(['dnsSettings', 'geodata', 'outboundGroups.fallbackGroup', 'routes'])
            ->get();

        $entries = collect($this->buildBaseProfileEntries($builder, $nodes))
            ->merge($customConfigs->map(fn (XrayCustomConfig $config): array => [
                'type' => 'xray_custom_config',
                'id' => (int) $config->id,
                'sort_order' => (int) $config->sort_order,
                'profiles' => json_decode($builder->buildForCustomConfig($customNodes, $config)->content),
            ]))
            ->filter(fn (array $entry): bool => is_array($entry['profiles']))
            ->sort(fn (array $left, array $right): int => [
                $left['sort_order'],
                $left['type'],
                $left['id'],
            ] <=> [
                $right['sort_order'],
                $right['type'],
                $right['id'],
            ]);

        $profiles = $entries
            ->flatMap(fn (array $entry): array => $entry['profiles'])
            ->values()
            ->all();

        return new SubscriptionBuildResult(
            content: json_encode($profiles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '[]',
            contentType: 'application/json; charset=UTF-8',
            fileExtension: 'json',
        );
    }

    /**
     * @param  array<int, NormalizedNode>  $nodes
     * @return array<int, array{type:string,id:int,sort_order:int,profiles:array<int,mixed>}>
     */
    private function buildBaseProfileEntries(ConnectJsonBuilder $builder, array $nodes): array
    {
        return collect($nodes)
            ->groupBy(fn (NormalizedNode $node): string => $node->sourceType === 'external_subscription'
                ? 'external_subscription:'.(int) ($node->meta['external_subscription_id'] ?? 0)
                : 'server:'.$node->serverId)
            ->map(function ($group) use ($builder): array {
                $node = $group->first();
                $profiles = json_decode(
                    $builder->buildForSubscriptionType($group->all(), XrayRouting::SUBSCRIPTION_CONNECT)->content,
                );

                return [
                    'type' => $node->sourceType === 'external_subscription' ? 'external_subscription' : 'server',
                    'id' => $node->sourceType === 'external_subscription'
                        ? (int) ($node->meta['external_subscription_id'] ?? 0)
                        : $node->serverId,
                    'sort_order' => $node->sortGroupOrder,
                    'profiles' => is_array($profiles) ? $profiles : [],
                ];
            })
            ->values()
            ->all();
    }
}
