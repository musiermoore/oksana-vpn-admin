<?php

namespace App\Services;

use App\Models\PaymentPeriod;
use App\Models\User;
use App\Models\UserSubscription;

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
        if ($user->activeSubscription) {
            $this->renewActiveSubscription($user);

            return;
        }

        $this->startCurrentPeriodSubscription($user);
    }

    private function renewActiveSubscription(User $user): void
    {
        $latestSubscription = $user->latestSubscription;
        $activePeriod = PaymentPeriod::getActive();

        if (! $latestSubscription || ! $activePeriod) {
            return;
        }

        if ($latestSubscription->end_date > $activePeriod->end_date) {
            return;
        }

        $nextPeriod = PaymentPeriod::getNextAfterDate($latestSubscription->end_date);

        if (! $nextPeriod || $this->userHasEnoughBalance($user, (float) $nextPeriod->amount) === false) {
            return;
        }

        $this->createSubscriptionIfMissing($user, $nextPeriod->start_date, $nextPeriod->end_date);
    }

    private function startCurrentPeriodSubscription(User $user): void
    {
        $activePeriod = PaymentPeriod::getActive();

        if (! $activePeriod || $this->userHasEnoughBalance($user, (float) $activePeriod->amount) === false) {
            return;
        }

        $hasActivePeriodCovered = $user->subscriptions()
            ->whereDate('start_date', '<=', $activePeriod->end_date)
            ->whereDate('end_date', '>=', $activePeriod->start_date)
            ->exists();

        if ($hasActivePeriodCovered) {
            return;
        }

        $this->createSubscriptionIfMissing($user, $activePeriod->start_date, $activePeriod->end_date);
    }

    private function createSubscriptionIfMissing(User $user, string $startDate, string $endDate): void
    {
        UserSubscription::firstOrCreate([
            'user_id' => $user->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    private function userHasEnoughBalance(User $user, float $requiredAmount): bool
    {
        return $user->getBalanceAmount() >= $requiredAmount;
    }
}
