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

        return $this->builderFactory
            ->make((string) $format)
            ->build($namedNodes);
    }
}
