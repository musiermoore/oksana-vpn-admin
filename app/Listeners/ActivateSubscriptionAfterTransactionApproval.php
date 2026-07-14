<?php

namespace App\Listeners;

use App\Enums\SubscriptionPurchaseType;
use App\Events\TransactionApproved;
use App\Jobs\DispatchDefaultConfigsForUserJob;
use App\Models\User;
use App\Services\SubscriptionCodeService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Artisan;

class ActivateSubscriptionAfterTransactionApproval
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly SubscriptionCodeService $subscriptionCodes,
    ) {}

    public function handle(TransactionApproved $event): void
    {
        $transaction = $event->transaction;

        if (! $transaction->is_approved || (float) $transaction->amount <= 0) {
            return;
        }

        if (SubscriptionPurchaseType::tryFrom((string) data_get($transaction->extra_data, 'purchase_type'))?->isGift()) {
            $buyer = User::query()->find($transaction->user_id);

            if (! $buyer) {
                return;
            }

            $months = (int) data_get($transaction->extra_data, 'subscription_months');
            $packagePrice = (float) data_get($transaction->extra_data, 'package_price');

            if ($months > 0 && $packagePrice > 0) {
                $this->subscriptionCodes->issueCodeForUser($buyer, $months, $packagePrice, $transaction);
            }

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
        Artisan::call('configs:disable-overdue-debtors', [
            'user_id' => $user->id,
        ]);
    }
}
