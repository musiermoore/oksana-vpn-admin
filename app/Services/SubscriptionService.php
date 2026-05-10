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
        $this->renewOrCreateSubscription($user);
    }

    private function renewOrCreateSubscription(User $user): void
    {
        $activePeriod = PaymentPeriod::getActive();
        $latestSubscription = $user->latestSubscription;

        if (! $activePeriod) {
            return;
        }

        $renewalDate = $latestSubscription
            ? Carbon::parse($latestSubscription->end_date)->subDay()->startOfDay()
            : today()->startOfDay();

        if (today()->lt($renewalDate)) {
            return;
        }

        $amount = $this->getRenewalAmount($user, $activePeriod->id, (float) $activePeriod->amount);
        if ($this->userHasEnoughBalance($user, $amount) === false) {
            return;
        }

        $startDate = $latestSubscription
            ? Carbon::parse($latestSubscription->end_date)->addDay()->toDateString()
            : today()->toDateString();
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

    private function getRenewalAmount(User $user, int $paymentPeriodId, float $baseAmount): float
    {
        $extraAmount = (float) $user->extraPayments()
            ->where('current_payment_id', $paymentPeriodId)
            ->sum('amount');

        return $baseAmount + $extraAmount;
    }

    private function userHasEnoughBalance(User $user, float $requiredAmount): bool
    {
        return $user->getBalanceAmount() >= $requiredAmount;
    }
}
