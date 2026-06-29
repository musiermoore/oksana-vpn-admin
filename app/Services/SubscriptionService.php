<?php

namespace App\Services;

use App\Models\PaymentPeriod;
use App\Models\SubscriptionCode;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    private const TRIAL_PACKAGE_MONTHS = 0;
    private const TRIAL_PACKAGE_DAYS = 2;

    private const PACKAGE_DISCOUNTS = [
        1 => 0,
        3 => 10,
        6 => 20,
        12 => 30,
    ];

    public function __construct(
        private readonly ReferralService $referrals,
    ) {}

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

    public function getSupportedPackageMonths(): array
    {
        return array_keys(self::PACKAGE_DISCOUNTS);
    }

    public function getPackagePricingForUser(User $user): array
    {
        $packages = array_map(function (int $months) use ($user): array {
            $quote = $this->buildPurchaseQuote($user, $months);

            return [
                'month' => $months,
                'days' => null,
                'price' => (float) $quote['package_price'],
                'payable_now' => (float) $quote['deposit_amount'],
                'balance_before' => (float) $quote['balance_before'],
                'balance_applied' => (float) round(max(0, $quote['package_price'] - $quote['deposit_amount']), 2),
                'discount_percent' => (int) $quote['discount_percent'],
                'is_trial' => false,
            ];
        }, $this->getSupportedPackageMonths());

        if ($this->isTrialAvailableForUser($user)) {
            array_unshift($packages, [
                'month' => self::TRIAL_PACKAGE_MONTHS,
                'days' => self::TRIAL_PACKAGE_DAYS,
                'price' => 0.0,
                'payable_now' => 0.0,
                'balance_before' => (float) round($user->getStoredBalanceAmount(), 2),
                'balance_applied' => 0.0,
                'discount_percent' => 0,
                'is_trial' => true,
            ]);
        }

        return $packages;
    }

    public function buildPurchaseQuote(User $user, int $months): array
    {
        if ($months === self::TRIAL_PACKAGE_MONTHS) {
            if (! $this->isTrialAvailableForUser($user)) {
                throw new \DomainException('Пробная подписка больше недоступна для этого аккаунта.');
            }

            return [
                'months' => self::TRIAL_PACKAGE_MONTHS,
                'days' => self::TRIAL_PACKAGE_DAYS,
                'discount_percent' => 0,
                'base_month_price' => 0.0,
                'package_full_price' => 0.0,
                'price_before_referral_discount' => 0.0,
                'package_price' => 0.0,
                'balance_before' => round($user->getStoredBalanceAmount(), 2),
                'deposit_amount' => 0.0,
                'referral_accumulated_discount_percent' => 0,
                'referral_permanent_discount_percent' => 0,
                'referral_total_discount_percent' => 0,
                'referral_discount_amount' => 0.0,
                'is_trial' => true,
            ];
        }

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
        $packagePriceBeforeReferral = round($packageFullPrice * ((100 - $discountPercent) / 100), 2);
        $referralSummary = $this->referrals->getSummary($user);
        $referralTotalDiscountPercent = (int) $referralSummary['total_discount_percent'];
        $referralDiscountAmount = round($packagePriceBeforeReferral * ($referralTotalDiscountPercent / 100), 2);
        $packagePrice = round(max(0, $packagePriceBeforeReferral - $referralDiscountAmount), 2);
        $balanceBefore = round($user->getStoredBalanceAmount(), 2);
        $depositAmount = round(max(0, $packagePrice - $balanceBefore), 2);

        return [
            'months' => $months,
            'discount_percent' => $discountPercent,
            'base_month_price' => round($baseMonthPrice, 2),
            'package_full_price' => $packageFullPrice,
            'price_before_referral_discount' => $packagePriceBeforeReferral,
            'package_price' => $packagePrice,
            'balance_before' => $balanceBefore,
            'deposit_amount' => $depositAmount,
            'referral_accumulated_discount_percent' => (int) $referralSummary['accumulated_discount_percent'],
            'referral_permanent_discount_percent' => (int) $referralSummary['permanent_discount_percent'],
            'referral_total_discount_percent' => $referralTotalDiscountPercent,
            'referral_discount_amount' => $referralDiscountAmount,
            'is_trial' => false,
        ];
    }

    public function activateTrialForUser(User $user): UserSubscription
    {
        if (! $this->isTrialAvailableForUser($user)) {
            throw new \DomainException('Пробная подписка больше недоступна для этого аккаунта.');
        }

        return DB::transaction(function () use ($user) {
            $startDate = today()->toDateString();
            $endDate = Carbon::parse($startDate)->addDays(self::TRIAL_PACKAGE_DAYS)->toDateString();

            $transaction = $user->transactions()->create([
                'type_id' => TransactionType::idBySlug(TransactionType::SLUG_SUBSCRIPTION),
                'amount' => 0,
                'is_approved' => true,
                'description' => 'Пробная подписка на 2 дня',
                'extra_data' => [
                    'subscription_days' => self::TRIAL_PACKAGE_DAYS,
                    'package_price' => 0,
                    'is_trial' => true,
                ],
            ]);

            $subscription = UserSubscription::query()->create([
                'user_id' => $user->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'price' => 0,
                'source' => 'trial',
                'transaction_id' => $transaction->id,
                'meta' => [
                    'subscription_days' => self::TRIAL_PACKAGE_DAYS,
                    'is_trial' => true,
                ],
            ]);

            $this->syncUserSubscriptionExpiry($user);

            return $subscription;
        });
    }

    public function isTrialAvailableForUser(User $user): bool
    {
        return ! $user->subscriptions()->exists();
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

            $this->syncUserSubscriptionExpiry($user);

            $extraData['package_activation_processed'] = true;
            $extraData['subscription_start_date'] = $startDate;
            $extraData['subscription_end_date'] = $endDate;

            $lockedTransaction->update([
                'extra_data' => $extraData,
            ]);
        });

        return true;
    }

    public function activatePackageForUser(
        User $user,
        int $months,
        float $packagePrice,
        array $purchaseMeta = [],
    ): UserSubscription {
        return $this->createPackageSubscription(
            user: $user,
            months: $months,
            packagePrice: $packagePrice,
            purchaseMeta: $purchaseMeta,
        );
    }

    public function grantBonusDays(
        User $user,
        int $days,
        ?Transaction $transaction = null,
        array $meta = [],
    ): UserSubscription {
        return DB::transaction(function () use ($user, $days, $transaction, $meta) {
            $user->loadMissing('latestSubscription');

            $latestSubscription = $user->latestSubscription;
            $startDate = $latestSubscription
                ? Carbon::parse($latestSubscription->end_date)->addDay()->toDateString()
                : today()->toDateString();
            $endDate = Carbon::parse($startDate)->addDays($days)->toDateString();

            $subscription = UserSubscription::query()->create([
                'user_id' => $user->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'price' => 0,
                'source' => 'referral_bonus',
                'transaction_id' => $transaction?->id,
                'meta' => $meta,
            ]);

            $this->syncUserSubscriptionExpiry($user);

            return $subscription;
        });
    }

    public function activateGiftCodeForUser(User $user, SubscriptionCode $code): UserSubscription
    {
        return DB::transaction(function () use ($user, $code) {
            $user->loadMissing('latestSubscription');

            $latestSubscription = $user->latestSubscription;
            $startDate = $latestSubscription
                ? Carbon::parse($latestSubscription->end_date)->addDay()->toDateString()
                : today()->toDateString();

            $endDate = $code->months
                ? Carbon::parse($startDate)->addMonths($code->months)->toDateString()
                : Carbon::parse($startDate)->addDays((int) $code->days)->toDateString();

            $subscription = UserSubscription::query()->create([
                'user_id' => $user->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'price' => (float) $code->price,
                'source' => 'gift_code',
                'meta' => [
                    'gift_code_id' => $code->id,
                    'gift_code' => $code->code,
                    'buyer_user_id' => $code->buyer_user_id,
                    'subscription_months' => $code->months,
                    'subscription_days' => $code->days,
                ],
            ]);

            $this->syncUserSubscriptionExpiry($user);

            return $subscription;
        });
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
                $this->syncUserSubscriptionExpiry($user);

                return;
            }

            $user->transactions()->create([
                'type_id' => TransactionType::idBySlug(TransactionType::SLUG_SUBSCRIPTION),
                'amount' => -$price,
                'is_approved' => true,
                'description' => 'Продление подписки',
            ]);

            $this->syncUserSubscriptionExpiry($user);
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
        array $purchaseMeta = [],
    ): UserSubscription {
        return DB::transaction(function () use ($user, $months, $packagePrice, $transaction, $purchaseMeta) {
            $lockedTransaction = $transaction
                ? Transaction::query()->lockForUpdate()->find($transaction->id)
                : null;

            if ($transaction && ! $lockedTransaction) {
                throw new \RuntimeException('Не удалось заблокировать транзакцию для активации подписки.');
            }

            $extraData = $lockedTransaction?->extra_data ?? $purchaseMeta;
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
                    'source' => 'purchase',
                    'transaction_id' => $lockedTransaction?->id,
                    'meta' => [
                        'subscription_months' => $months,
                    ],
                ]
            );

            if ($subscription->wasRecentlyCreated) {
                $subscriptionTransaction = $user->transactions()->create([
                    'type_id' => TransactionType::idBySlug(TransactionType::SLUG_SUBSCRIPTION),
                    'amount' => -$packagePrice,
                    'is_approved' => true,
                    'description' => $this->buildPackageTransactionDescription($months),
                    'extra_data' => [
                        'subscription_months' => $months,
                        'package_price' => $packagePrice,
                        'referral_accumulated_discount_percent_used' => (int) data_get($extraData, 'referral_accumulated_discount_percent', 0),
                        'referral_permanent_discount_percent_used' => (int) data_get($extraData, 'referral_permanent_discount_percent', 0),
                        'referral_total_discount_percent_used' => (int) data_get($extraData, 'referral_total_discount_percent', 0),
                        'referral_discount_amount' => (float) data_get($extraData, 'referral_discount_amount', 0),
                    ],
                ]);

                $subscription->forceFill([
                    'transaction_id' => $subscriptionTransaction->id,
                ])->save();

                if ((int) data_get($extraData, 'referral_accumulated_discount_percent', 0) > 0) {
                    $user->forceFill([
                        'referral_accumulated_discount_percent' => 0,
                    ])->save();
                }

                app(ReferralRewardService::class)->scheduleForSubscriptionPurchase($user, $subscriptionTransaction);
            }

            if ($lockedTransaction) {
                $extraData['package_activation_processed'] = true;
                $extraData['subscription_start_date'] = $startDate;
                $extraData['subscription_end_date'] = $endDate;
                $extraData['subscription_transaction_id'] = $subscription->transaction_id;

                $lockedTransaction->update([
                    'extra_data' => $extraData,
                ]);
            }

            $this->syncUserSubscriptionExpiry($user);

            return $subscription;
        });
    }

    private function syncUserSubscriptionExpiry(User $user): void
    {
        $expiresAt = UserSubscription::query()
            ->where('user_id', $user->id)
            ->max('end_date');

        $user->forceFill([
            'subscription_expires_at' => $expiresAt ? Carbon::parse($expiresAt)->endOfDay() : null,
        ])->save();
    }
}
