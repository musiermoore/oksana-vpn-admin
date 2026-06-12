<?php

namespace App\Services;

use App\Models\PaymentPeriod;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    private const PACKAGE_DISCOUNTS = [
        1 => 0,
        3 => 10,
        6 => 20,
        12 => 30,
    ];

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

    public function buildPurchaseQuote(User $user, int $months): array
    {
        $discountPercent = self::PACKAGE_DISCOUNTS[$months] ?? null;
        $activePeriod = PaymentPeriod::getActive();

        if ($discountPercent === null) {
            throw new \DomainException('Поддерживаются только пакеты на 1, 3, 6 или 12 месяцев.');
        }

        if (! $activePeriod) {
            throw new \DomainException('Сейчас нет активного периода оплаты для расчёта стоимости.');
        }

        $baseMonthPrice = $this->getRenewalAmount($user, $activePeriod->id, (float) $activePeriod->amount);
        $packageFullPrice = round($baseMonthPrice * $months, 2);
        $packagePrice = round($packageFullPrice * ((100 - $discountPercent) / 100), 2);
        $balanceBefore = round($user->getStoredBalanceAmount(), 2);
        $depositAmount = round(max(0, $packagePrice - $balanceBefore), 2);

        return [
            'months' => $months,
            'discount_percent' => $discountPercent,
            'base_month_price' => round($baseMonthPrice, 2),
            'package_full_price' => $packageFullPrice,
            'package_price' => $packagePrice,
            'balance_before' => $balanceBefore,
            'deposit_amount' => $depositAmount,
        ];
    }

    public function activatePurchasedMonthsForTransaction(User $user, Transaction $transaction): bool
    {
        $months = (int) data_get($transaction->extra_data, 'subscription_months');
        $packagePrice = (float) data_get($transaction->extra_data, 'package_price');

        if (! array_key_exists($months, self::PACKAGE_DISCOUNTS) || $packagePrice <= 0) {
            return false;
        }

        DB::transaction(function () use ($user, $transaction, $packagePrice, $months) {
            $lockedTransaction = Transaction::query()
                ->lockForUpdate()
                ->find($transaction->id);

            if (! $lockedTransaction) {
                return;
            }

            $extraData = $lockedTransaction->extra_data ?? [];
            if (($extraData['package_activation_processed'] ?? false) === true) {
                return;
            }

            $user->loadMissing('latestSubscription');

            $latestSubscription = $user->latestSubscription;
            $startDate = $latestSubscription
                ? Carbon::parse($latestSubscription->end_date)->addDay()->toDateString()
                : today()->toDateString();
            $endDate = Carbon::parse($startDate)->addMonths($months)->toDateString();

            $subscription = UserSubscription::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                [
                    'price' => $packagePrice,
                ]
            );

            if ($subscription->wasRecentlyCreated) {
                $user->transactions()->create([
                    'type_id' => TransactionType::idBySlug(TransactionType::SLUG_SUBSCRIPTION),
                    'amount' => -$packagePrice,
                    'is_approved' => true,
                    'description' => $this->buildPackageTransactionDescription($months),
                ]);
            }

            $extraData['package_activation_processed'] = true;
            $extraData['subscription_start_date'] = $startDate;
            $extraData['subscription_end_date'] = $endDate;

            $lockedTransaction->update([
                'extra_data' => $extraData,
            ]);
        });

        return true;
    }

    public function activatePackageForUser(User $user, int $months, float $packagePrice): UserSubscription
    {
        return $this->createPackageSubscription(
            user: $user,
            months: $months,
            packagePrice: $packagePrice,
        );
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

    private function buildPackageTransactionDescription(int $months): string
    {
        return "Покупка подписки на $months мес.";
    }

    private function createPackageSubscription(
        User $user,
        int $months,
        float $packagePrice,
        ?Transaction $transaction = null,
    ): UserSubscription {
        return DB::transaction(function () use ($user, $months, $packagePrice, $transaction) {
            $lockedTransaction = $transaction
                ? Transaction::query()->lockForUpdate()->find($transaction->id)
                : null;

            if ($transaction && ! $lockedTransaction) {
                throw new \RuntimeException('Не удалось заблокировать транзакцию для активации подписки.');
            }

            $extraData = $lockedTransaction?->extra_data ?? [];
            if (($extraData['package_activation_processed'] ?? false) === true) {
                $subscriptionStartDate = $extraData['subscription_start_date'] ?? null;
                $subscriptionEndDate = $extraData['subscription_end_date'] ?? null;

                return UserSubscription::query()
                    ->where('user_id', $user->id)
                    ->where('start_date', $subscriptionStartDate)
                    ->where('end_date', $subscriptionEndDate)
                    ->firstOrFail();
            }

            $user->loadMissing('latestSubscription');

            $latestSubscription = $user->latestSubscription;
            $startDate = $latestSubscription
                ? Carbon::parse($latestSubscription->end_date)->addDay()->toDateString()
                : today()->toDateString();
            $endDate = Carbon::parse($startDate)->addMonths($months)->toDateString();

            $subscription = UserSubscription::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                [
                    'price' => $packagePrice,
                ]
            );

            if ($subscription->wasRecentlyCreated) {
                $user->transactions()->create([
                    'type_id' => TransactionType::idBySlug(TransactionType::SLUG_SUBSCRIPTION),
                    'amount' => -$packagePrice,
                    'is_approved' => true,
                    'description' => $this->buildPackageTransactionDescription($months),
                ]);
            }

            if ($lockedTransaction) {
                $extraData['package_activation_processed'] = true;
                $extraData['subscription_start_date'] = $startDate;
                $extraData['subscription_end_date'] = $endDate;

                $lockedTransaction->update([
                    'extra_data' => $extraData,
                ]);
            }

            return $subscription;
        });
    }
}
