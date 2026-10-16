<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Subscription\NormalizedNode;
use App\Models\SubscriptionProfileHealthCheck;
use App\Models\User;
use App\Models\VlessExternalSubscription;
use App\Services\ExternalSubscriptions\VlessExternalSubscriptionAccessService;
use App\Services\ExternalSubscriptions\VlessExternalSubscriptionSyncService;

final class ExternalSubscriptionProfileHealthChecker
{
    public function __construct(
        private readonly VlessExternalSubscriptionAccessService $externalSubscriptions,
        private readonly SubscriptionProfileHealthRecorder $recorder,
    ) {}

    public function check(User $user): void
    {
        $this->recorder->runFlow('external', fn () => $this->checkExternalConfigs($user));
        $this->recorder->runFlow('external-source', fn () => $this->checkExternalSources());
    }

    private function checkExternalConfigs(User $user): void
    {
        $nodes = [
            ...$this->externalSubscriptions->getNamedNodesForUserByPurpose(
                $user,
                VlessExternalSubscriptionSyncService::PURPOSE_MAIN,
            ),
            ...$this->externalSubscriptions->getNamedNodesForUserByPurpose(
                $user,
                VlessExternalSubscriptionSyncService::PURPOSE_CONNECT_V2,
            ),
            ...$this->externalSubscriptions->getNamedNodesForUserByPurpose(
                $user,
                VlessExternalSubscriptionSyncService::PURPOSE_WHITELIST,
            ),
        ];
        $nodes = collect($nodes)->unique(fn (NormalizedNode $node): string => $node->id)->values()->all();

        foreach ($nodes as $node) {
            $this->recorder->queueProbe(
                'external',
                $this->nodeKey($node),
                (string) ($node->meta['name'] ?? $node->serverName),
                'external-node',
                $node,
            );
        }

        $this->recorder->recordEmpty('external', $nodes);
    }

    private function checkExternalSources(): void
    {
        $sources = VlessExternalSubscription::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('include_in_main_subscription', true)
                    ->orWhere('include_in_whitelist', true)
                    ->orWhere('include_in_connect_v2', true);
            })
            ->get();

        foreach ($sources as $source) {
            $status = $source->last_sync_error !== null
                ? SubscriptionProfileHealthCheck::STATUS_UNHEALTHY
                : ($source->is_ready && $source->last_synced_at !== null
                    ? SubscriptionProfileHealthCheck::STATUS_HEALTHY
                    : SubscriptionProfileHealthCheck::STATUS_UNKNOWN);

            $this->recorder->record('external-source', 'source-'.$source->id, $source->name, [
                'status' => $status,
                'results' => [
                    'sync' => [
                        'ok' => $status === SubscriptionProfileHealthCheck::STATUS_HEALTHY,
                        'last_synced_at' => $source->last_synced_at?->toIso8601String(),
                        'error' => $source->last_sync_error,
                    ],
                ],
                'error' => $source->last_sync_error,
            ]);
        }

        $this->recorder->recordEmpty('external-source', $sources->all());
    }

    private function nodeKey(NormalizedNode $node): string
    {
        return sha1($node->sourceType.'|'.$node->id.'|'.$node->uri);
    }
}
