<?php

namespace App\Listeners;

use App\Events\TransactionApproved;
use App\Jobs\DispatchDefaultConfigsForUserJob;
use App\Models\User;
use App\Services\SubscriptionService;

class ActivateSubscriptionAfterTransactionApproval
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
    ) {}

    public function handle(TransactionApproved $event): void
    {
        $transaction = $event->transaction;

        if (! $transaction->is_approved || (float) $transaction->amount <= 0) {
            return;
        }

        $user = User::query()
            ->with(['activeSubscription', 'latestSubscription'])
            ->find($transaction->user_id);

        if (! $user) {
            return;
        }

        $packageWasActivated = $this->subscriptionService->activatePurchasedMonthsForTransaction($user, $transaction);

        if (! $packageWasActivated) {
            $this->subscriptionService->renewForUser($user);
        }

        $user->load('activeSubscription');

        if (! $user->hasActiveSubscription()) {
            return;
        }

        DispatchDefaultConfigsForUserJob::dispatch($user->id);
    }
}
