<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\DTOs\Subscription\NormalizedNode;
use App\DTOs\Subscription\SubscriptionBuildResult;
use App\Models\SubscriptionType;
use App\Models\SubscriptionTypeItem;
use App\Models\User;
use App\Services\ExternalSubscriptions\VlessExternalSubscriptionAccessService;
use App\Services\ExternalSubscriptions\VlessExternalSubscriptionSyncService;
use Illuminate\Support\Collection;

final class SubscriptionTypeBuilderService
{
    public function __construct(
        private readonly NormalizedNodeService $nodes,
        private readonly VlessExternalSubscriptionAccessService $externalSubscriptions,
        private readonly UserSubscriptionService $subscriptions,
    ) {}

    public function build(User $user, SubscriptionType $type, ?string $client = null): SubscriptionBuildResult
    {
        $items = $type->items;
        $externalSubscriptionIds = $items
            ->where('source_type', SubscriptionTypeItem::SOURCE_EXTERNAL_SUBSCRIPTION)
            ->pluck('source_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
        $externalNodes = collect($this->externalSubscriptions->getNamedNodesForSubscriptionIds(
            $user,
            $externalSubscriptionIds,
            $type->kind !== SubscriptionType::KIND_CONNECT_FREE && $user->hasActiveAccess(),
        ));
        $visibilityField = match ($type->kind) {
            SubscriptionType::KIND_CONNECT => 'is_visible_in_connect',
            SubscriptionType::KIND_CONNECT_V2 => 'is_visible_in_connect_v2',
            default => null,
        };
        $availableNodes = collect($this->nodes->collect($user, $visibilityField));
        $selectedNodes = $this->selectNodes($items, $availableNodes, $externalNodes);
        if (
            $type->format === SubscriptionType::FORMAT_JSON
            && in_array($type->kind, [SubscriptionType::KIND_CONNECT_V2, SubscriptionType::KIND_CONNECT_FREE], true)
        ) {
            $selectedNodes = collect(ConnectV2ConfigGroupAnnotator::annotate($selectedNodes->all()));
        }
        $customItems = $items->where('source_type', SubscriptionTypeItem::SOURCE_XRAY_CUSTOM_CONFIG);
        $customConfigIds = $customItems->pluck('source_id')->map(fn (mixed $id): int => (int) $id)->all();
        $customConfigSortOrderById = $customItems->mapWithKeys(fn (SubscriptionTypeItem $item): array => [
            (int) $item->source_id => (int) $item->sort_order,
        ])->all();

        if ($type->show_expired_placeholder && ! $user->hasActiveAccess()) {
            $selectedNodes->prepend($this->expiredPlaceholder());
        }

        return $this->subscriptions->buildFromNodes(
            nodes: $selectedNodes->all(),
            format: $type->format,
            subscriptionType: $type->kind,
            user: $user,
            customExternalPurpose: $type->kind === SubscriptionType::KIND_CONNECT_FREE
                ? VlessExternalSubscriptionSyncService::PURPOSE_CONNECT_FREE
                : VlessExternalSubscriptionSyncService::PURPOSE_CUSTOM,
            client: $client,
            customConfigIds: $customConfigIds,
            customConfigSortOrderById: $customConfigSortOrderById,
        );
    }

    /**
     * @param Collection<int, SubscriptionTypeItem> $items
     * @param Collection<int, NormalizedNode> $availableNodes
     * @param Collection<int, NormalizedNode> $externalNodes
     * @return Collection<int, NormalizedNode>
     */
    private function selectNodes(Collection $items, Collection $availableNodes, Collection $externalNodes): Collection
    {
        return $items
            ->flatMap(function (SubscriptionTypeItem $item, int $itemIndex) use ($availableNodes, $externalNodes): Collection {
                $sourceId = (int) $item->source_id;

                $nodes = match ($item->source_type) {
                    SubscriptionTypeItem::SOURCE_SERVER => $availableNodes->filter(fn (NormalizedNode $node): bool =>
                        $node->sourceType !== 'external_subscription'
                        && ! isset($node->meta['proxy_id'])
                        && $node->serverId === $sourceId
                    ),
                    SubscriptionTypeItem::SOURCE_PROXY => $availableNodes->filter(fn (NormalizedNode $node): bool =>
                        (int) ($node->meta['proxy_id'] ?? 0) === $sourceId
                    ),
                    SubscriptionTypeItem::SOURCE_EXTERNAL_SUBSCRIPTION => $externalNodes->filter(fn (NormalizedNode $node): bool =>
                        (int) ($node->meta['external_subscription_id'] ?? 0) === $sourceId
                    ),
                    default => collect(),
                };

                return $nodes
                    ->values()
                    ->map(fn (NormalizedNode $node, int $index): NormalizedNode => $this->orderNode(
                        $node,
                        (int) $item->sort_order,
                        $index,
                        (int) ($item->getKey() ?? $itemIndex + 1),
                    ));
            })
            ->sort(fn (NormalizedNode $left, NormalizedNode $right): int => [
                $left->sortGroupOrder,
                $left->sortItemOrder,
                $left->serverId,
                $left->configId,
                $left->id,
            ] <=> [
                $right->sortGroupOrder,
                $right->sortItemOrder,
                $right->serverId,
                $right->configId,
                $right->id,
            ])
            ->values();
    }

    private function orderNode(NormalizedNode $node, int $sortOrder, int $itemIndex, int $subscriptionTypeItemId): NormalizedNode
    {
        return new NormalizedNode(
            id: $node->id,
            serverName: $node->serverName,
            sortGroupOrder: $sortOrder,
            sortItemOrder: $itemIndex,
            protocol: $node->protocol,
            transport: $node->transport,
            uri: $node->uri,
            serverId: $node->serverId,
            configId: $node->configId,
            sourceType: $node->sourceType,
            sortServerName: $node->sortServerName,
            meta: [...$node->meta, 'subscription_type_item_id' => $subscriptionTypeItemId],
        );
    }

    private function expiredPlaceholder(): NormalizedNode
    {
        $placeholder = $this->subscriptions->getExpiredPlaceholderNodes()[0];

        return new NormalizedNode(
            id: $placeholder->id,
            serverName: $placeholder->serverName,
            sortGroupOrder: -1,
            sortItemOrder: -1,
            protocol: $placeholder->protocol,
            transport: $placeholder->transport,
            uri: $placeholder->uri,
            serverId: $placeholder->serverId,
            configId: $placeholder->configId,
            sourceType: $placeholder->sourceType,
            sortServerName: $placeholder->sortServerName,
            meta: $placeholder->meta,
        );
    }
}
