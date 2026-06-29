<?php

namespace App\Services;

use App\Enums\SubscriptionPurchaseType;
use App\Jobs\DispatchDefaultConfigsForUserJob;
use App\Models\PaymentPeriod;
use App\Models\SubscriptionCode;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class SubscriptionCodeService
{
    private const PACKAGE_DISCOUNTS = [
        1 => 0,
        3 => 10,
        6 => 20,
        12 => 30,
    ];

    private const PACKAGE_DAYS = [
        1 => 30,
        3 => 90,
        6 => 180,
        12 => 365,
    ];

    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function buildPurchaseQuote(User $user, int $months): array
    {
        $discountPercent = self::PACKAGE_DISCOUNTS[$months] ?? null;
        $activePeriod = PaymentPeriod::getActive();

        if ($discountPercent === null) {
            throw new DomainException('Поддерживаются только пакеты на 1, 3, 6 или 12 месяцев.');
        }

        if (! $activePeriod) {
            throw new DomainException('Сейчас нет активного периода оплаты для расчёта стоимости.');
        }

        $baseMonthPrice = round((float) $activePeriod->amount, 2);
        $packageFullPrice = round($baseMonthPrice * $months, 2);
        $packagePrice = round($packageFullPrice * ((100 - $discountPercent) / 100), 2);
        $balanceBefore = round($user->getStoredBalanceAmount(), 2);
        $depositAmount = round(max(0, $packagePrice - $balanceBefore), 2);

        return [
            'months' => $months,
            'days' => self::PACKAGE_DAYS[$months] ?? null,
            'discount_percent' => $discountPercent,
            'base_month_price' => $baseMonthPrice,
            'package_full_price' => $packageFullPrice,
            'package_price' => $packagePrice,
            'balance_before' => $balanceBefore,
            'deposit_amount' => $depositAmount,
        ];
    }

    public function issueCodeForUser(
        User $buyer,
        int $months,
        float $packagePrice,
        ?Transaction $purchaseTransaction = null,
        array $purchaseMeta = [],
    ): SubscriptionCode {
        return DB::transaction(function () use ($buyer, $months, $packagePrice, $purchaseTransaction, $purchaseMeta) {
            $lockedTransaction = $purchaseTransaction
                ? Transaction::query()->lockForUpdate()->find($purchaseTransaction->id)
                : null;

            if ($purchaseTransaction && ! $lockedTransaction) {
                throw new \RuntimeException('Не удалось заблокировать транзакцию для выдачи подарочного кода.');
            }

            $extraData = $lockedTransaction?->extra_data ?? $purchaseMeta;
            $existingCodeId = (int) data_get($extraData, 'gift_code_id', 0);

            if ($existingCodeId > 0) {
                return SubscriptionCode::query()->findOrFail($existingCodeId);
            }

            $code = SubscriptionCode::query()->create([
                'buyer_user_id' => $buyer->id,
                'code' => $this->generateUniqueCode(),
                'months' => $months,
                'days' => self::PACKAGE_DAYS[$months] ?? null,
                'price' => $packagePrice,
                'status' => SubscriptionCode::STATUS_ACTIVE,
                'meta' => [
                    'subscription_months' => $months,
                    'purchase_type' => SubscriptionPurchaseType::GIFT->value,
                ],
            ]);

            $subscriptionTransaction = $buyer->transactions()->create([
                'type_id' => TransactionType::idBySlug(TransactionType::SLUG_SUBSCRIPTION),
                'amount' => -$packagePrice,
                'is_approved' => true,
                'description' => $this->buildGiftTransactionDescription($months),
                'extra_data' => [
                    'purchase_type' => SubscriptionPurchaseType::GIFT->value,
                    'subscription_months' => $months,
                    'package_price' => $packagePrice,
                    'gift_code_id' => $code->id,
                    'gift_code' => $code->code,
                ],
            ]);

            $code->forceFill([
                'transaction_id' => $subscriptionTransaction->id,
            ])->save();

            if ($lockedTransaction) {
                $extraData['gift_code_id'] = $code->id;
                $extraData['gift_code'] = $code->code;
                $extraData['gift_code_generated'] = true;
                $extraData['subscription_transaction_id'] = $subscriptionTransaction->id;

                $lockedTransaction->update([
                    'extra_data' => $extraData,
                ]);
            }

            return $code->refresh();
        });
    }

    public function activateForUser(User $user, string $rawCode): SubscriptionCode
    {
        return DB::transaction(function () use ($user, $rawCode) {
            $normalizedCode = $this->normalizeCode($rawCode);

            $code = SubscriptionCode::query()
                ->where('code', $normalizedCode)
                ->lockForUpdate()
                ->first();

            if (! $code) {
                throw new DomainException('Код не найден. Проверьте ввод и попробуйте ещё раз.');
            }

            if ($code->buyer_user_id === $user->id) {
                throw new DomainException('Свой подарочный код нельзя активировать на тот же аккаунт.');
            }

            if ($code->isActivated()) {
                throw new DomainException('Этот код уже был активирован.');
            }

            if ($code->expires_at && $code->expires_at->isPast()) {
                throw new DomainException('Срок действия этого кода истёк.');
            }

            $subscription = $this->subscriptions->activateGiftCodeForUser($user, $code);

            $code->forceFill([
                'activated_by_user_id' => $user->id,
                'status' => SubscriptionCode::STATUS_ACTIVATED,
                'activated_at' => now(),
                'meta' => [
                    ...($code->meta ?? []),
                    'activated_subscription_id' => $subscription->id,
                ],
            ])->save();

            $user->load('activeSubscription');

            if ($user->hasActiveSubscription()) {
                DispatchDefaultConfigsForUserJob::dispatch($user->id);
            }

            return $code->refresh();
        });
    }

    public function normalizeCode(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $value) ?? '');
    }

    private function generateUniqueCode(): string
    {
        do {
            $raw = collect(range(1, 12))
                ->map(fn () => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[random_int(0, 31)])
                ->implode('');
        } while (SubscriptionCode::query()->where('code', $raw)->exists());

        return $raw;
    }

    private function buildGiftTransactionDescription(int $months): string
    {
        return "Подарочный код на {$months} мес.";
    }
}
