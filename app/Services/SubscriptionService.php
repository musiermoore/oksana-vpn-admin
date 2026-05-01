<?php

namespace App\Services;

use App\Models\PaymentPeriod;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function renewEligibleSubscriptions(): void
    {
        $users = User::query()
            ->with(['activeSubscription', 'latestSubscription'])
            ->get();

        foreach ($users as $user) {
            $this->renewForUser($user);
        }
    }

    public function renewForUser(User $user): void
    {
        $this->renewActiveSubscription($user);
    }

    private function renewActiveSubscription(User $user): void
    {
        $activePeriod = PaymentPeriod::getActive();
        $activeSubscription = $user->activeSubscription;
        $latestSubscription = $user->latestSubscription;

        if (! $activeSubscription || ! $latestSubscription || ! $activePeriod) {
            return;
        }

        if ($latestSubscription->id !== $activeSubscription->id) {
            return;
        }

        $renewalDate = Carbon::parse($activeSubscription->end_date)->subDay()->startOfDay();

        if (today()->lt($renewalDate)) {
            return;
        }

        $amount = (float) $activePeriod->amount;
        if ($this->userHasEnoughBalance($user, $amount) === false) {
            return;
        }

        $startDate = Carbon::parse($activeSubscription->end_date)->addDay()->toDateString();
        $endDate = Carbon::parse($startDate)->addMonth()->toDateString();

        $this->createSubscriptionIfMissing($user, $startDate, $endDate, $amount);
    }

    private function createSubscriptionIfMissing(User $user, string $startDate, string $endDate, float $price): void
    {
        DB::transaction(function () use ($user, $startDate, $endDate, $price) {
            $subscription = UserSubscription::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                [
                    'price' => $price,
                ]
            );

            if (! $subscription->wasRecentlyCreated) {
                return;
            }

            $user->transactions()->create([
                'type_id' => TransactionType::idBySlug(TransactionType::SLUG_SUBSCRIPTION),
                'amount' => -$price,
                'is_approved' => true,
                'description' => 'Продление подписки',
            ]);
        });
    }

    private function userHasEnoughBalance(User $user, float $requiredAmount): bool
    {
        return $user->getBalanceAmount() >= $requiredAmount;
    }
}
