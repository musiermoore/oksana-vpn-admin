<?php

namespace App\Services\Subscriptions;

use App\DTOs\Subscription\NormalizedNode;
use App\DTOs\Subscription\SubscriptionBuildResult;
use App\Models\User;

class UserSubscriptionService
{
    public function __construct(
        private readonly NormalizedNodeService $normalizedNodeService,
        private readonly NodeNameService $nodeNameService,
        private readonly SubscriptionBuilderFactory $builderFactory,
    ) {}

    public function build(User $user, ?string $format = null): SubscriptionBuildResult
    {
        $nodes = $this->normalizedNodeService->collect($user);
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

        return $this->builderFactory
            ->make((string) $format)
            ->build($namedNodes);
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
