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
        return $this->buildOutput($user, $type, $this->collectOutputNodes($user, $type), $type->format, $client);
    }

    public function buildStatic(SubscriptionType $type, ?string $client = null): SubscriptionBuildResult
    {
        return $this->buildOutput(null, $type, $this->collectOutputNodes(null, $type), $type->format, $client);
    }

    /** @return array{result:SubscriptionBuildResult, profiles:array<int, array{name:string,category:?string,subtitle:?string}>, json:string} */
    public function buildPreview(?User $user, SubscriptionType $type): array
    {
        $nodes = ConnectV2ConfigGroupAnnotator::annotate($this->collectNodes($user, $type));
        $result = $this->buildOutput($user, $type, $nodes, $type->format);
        $isJson = $type->format === SubscriptionType::FORMAT_JSON;
        $json = str_starts_with($result->contentType, 'application/json')
            ? $result->content
            : $this->buildOutput($user, $type, $nodes, SubscriptionType::FORMAT_JSON)->content;

        if ($isJson) {
            $displayJson = $type->kind === SubscriptionType::KIND_CONNECT
                ? $this->buildOutput($user, $type, $nodes, $type->format, includeConfigGroup: true)->content
                : $json;
            $profiles = array_map(static fn (array $profile): array => [
                'name' => $profile['remarks'] ?? '',
                'category' => $profile['group']['name'] ?? null,
                'subtitle' => $profile['subtitle'] ?? null,
            ], json_decode($displayJson, true, flags: JSON_THROW_ON_ERROR));
        } else {
            $profiles = array_map(static fn (NormalizedNode $node): array => [
                'name' => $node->meta['name'] ?? $node->serverName,
                'category' => $node->meta['config_group']['name'] ?? null,
                'subtitle' => $node->meta['subtitle'] ?? null,
            ], $nodes);
        }

        return ['result' => $result, 'profiles' => $profiles, 'json' => $json];
    }

    /** @return array<int, NormalizedNode> */
    private function collectOutputNodes(?User $user, SubscriptionType $type): array
    {
        $nodes = $this->collectNodes($user, $type);

        if ($type->format === SubscriptionType::FORMAT_JSON
            && in_array($type->kind, [SubscriptionType::KIND_CONNECT_V2, SubscriptionType::KIND_CONNECT_FREE], true)) {
            return ConnectV2ConfigGroupAnnotator::annotate($nodes);
        }

        return $nodes;
    }

    /** @return array<int, NormalizedNode> */
    private function collectNodes(?User $user, SubscriptionType $type): array
    {
        $items = $type->items;
        $externalItems = $items->where('source_type', SubscriptionTypeItem::SOURCE_EXTERNAL_SUBSCRIPTION);
        $externalNodes = collect($this->externalSubscriptions->getNamedNodesForSubscriptionIds(
            $user,
            $externalItems->pluck('source_id')->all(),
            $this->hasActiveAccess($user),
            $externalItems->where('is_free', true)->pluck('source_id')->all(),
        ));
        $visibilityField = match ($type->kind) {
            SubscriptionType::KIND_CONNECT => 'is_visible_in_connect',
            SubscriptionType::KIND_CONNECT_V2 => 'is_visible_in_connect_v2',
            default => null,
        };
        $availableNodes = $user === null ? collect() : collect($this->nodes->collect($user, $visibilityField));
        $selectedNodes = $this->selectNodes($items, $availableNodes, $externalNodes);

        if ($user !== null && $type->show_expired_placeholder && ! $this->hasActiveAccess($user)) {
            $selectedNodes->prepend($this->expiredPlaceholder());
        }

        return $selectedNodes->all();
    }

    /** @param array<int, NormalizedNode> $nodes */
    private function buildOutput(
        ?User $user,
        SubscriptionType $type,
        array $nodes,
        string $format,
        ?string $client = null,
        bool $includeConfigGroup = false,
    ): SubscriptionBuildResult {
        $customItems = $user === null
            ? collect()
            : $type->items->where('source_type', SubscriptionTypeItem::SOURCE_XRAY_CUSTOM_CONFIG);
        $customConfigSortOrderById = $customItems->mapWithKeys(fn (SubscriptionTypeItem $item): array => [
            $item->source_id => $item->sort_order,
        ])->all();

        return $this->subscriptions->buildFromNodes(
            nodes: $nodes,
            format: $format,
            subscriptionType: $type->kind,
            user: $user,
            customExternalPurpose: $type->kind === SubscriptionType::KIND_CONNECT_FREE
                ? VlessExternalSubscriptionSyncService::PURPOSE_CONNECT_FREE
                : VlessExternalSubscriptionSyncService::PURPOSE_CUSTOM,
            client: $client,
            customConfigIds: $customItems->pluck('source_id')->all(),
            customConfigSortOrderById: $customConfigSortOrderById,
            includePaidCustomConfigs: $this->hasActiveAccess($user),
            freeCustomConfigIds: $customItems->where('is_free', true)->pluck('source_id')->all(),
            includeConfigGroup: $includeConfigGroup,
        );
    }

    private function hasActiveAccess(?User $user): bool
    {
        return $user !== null && $user->hasActiveAccess() && ! $user->hasDebt();
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
