<?php

namespace App\Jobs;

use App\Models\VlessExternalSubscription;
use App\Services\ExternalSubscriptions\VlessExternalSubscriptionSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncVlessExternalSubscriptionJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $subscriptionId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->subscriptionId;
    }

    public function handle(VlessExternalSubscriptionSyncService $syncService): void
    {
        $subscription = VlessExternalSubscription::query()->find($this->subscriptionId);

        if (! $subscription || ! $subscription->is_active) {
            return;
        }

        $syncService->sync($subscription);
    }

    public function failed(Throwable $exception): void
    {
        $subscription = VlessExternalSubscription::query()->find($this->subscriptionId);

        if ($subscription) {
            app(VlessExternalSubscriptionSyncService::class)
                ->failSync($subscription, $exception->getMessage());
        }

        report($exception);
    }
}
