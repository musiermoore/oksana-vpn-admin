<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\PromoCode\PromoCodeQuote;
use App\Enums\PromoCodeBenefit;
use App\Models\PromoCodeRedemption;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Repositories\PromoCodeRepository;
use App\Repositories\TransactionRepository;
use DomainException;

class PromoCodeService
{
    public function __construct(
        private readonly PromoCodeRepository $promoCodes,
        private readonly TransactionRepository $transactions,
    ) {}

    public function quote(User $user, string $code, float $price, float $balance): PromoCodeQuote
    {
        $promoCode = $this->promoCodes->findByCode($code);
        $today = today();

        if (! $promoCode?->is_active || $promoCode->starts_on?->gt($today) || $promoCode->ends_on?->lt($today)) {
            throw new DomainException('Промокод недействителен или срок его действия истёк.');
        }

        if ($promoCode->new_users_only && $this->promoCodes->hasNonTrialSubscription($user)) {
            throw new DomainException('Промокод доступен только новым пользователям.');
        }

        if ($promoCode->once_per_user && $this->promoCodes->hasUse($promoCode, $user)) {
            throw new DomainException('Вы уже использовали этот промокод или начали оплату с ним.');
        }

        if ($promoCode->new_users_only && $this->promoCodes->hasPendingWelcomePromo($user)) {
            throw new DomainException('У вас уже есть незавершённая оплата с промокодом для новых пользователей.');
        }

        return new PromoCodeQuote($promoCode, $price, $balance);
    }

    public function cancelPendingPayment(Transaction $payment): void
    {
        if (data_get($payment->extra_data, 'promo_code_id') !== null) {
            $this->promoCodes->cancelPendingPayment($payment);
        }
    }

    public function reserve(PromoCodeQuote $quote, User $user, ?Transaction $payment = null): PromoCodeRedemption
    {
        return $this->promoCodes->reserve($quote, $user, $payment);
    }

    public function lockForPayment(Transaction $payment): ?PromoCodeRedemption
    {
        if (data_get($payment->extra_data, 'promo_code_id') === null) {
            return null;
        }

        $redemption = $this->promoCodes->lockForPayment($payment);

        if ($redemption->canceled_at !== null) {
            throw new DomainException('Оплата с промокодом уже отменена.');
        }

        return $redemption;
    }

    public function complete(User $user, PromoCodeRedemption $redemption, int $subscriptionTransactionId): void
    {
        if ($redemption->redeemed_at !== null) {
            return;
        }

        if ($redemption->benefit === PromoCodeBenefit::BalanceCashback) {
            $this->creditBalance($user, $redemption);
        }

        $redemption->update([
            'subscription_transaction_id' => $subscriptionTransactionId,
            'redeemed_at' => now(),
        ]);
    }

    public function applyCheckoutDeposit(User $user, ?PromoCodeRedemption $redemption): void
    {
        if ($redemption?->benefit === PromoCodeBenefit::BalanceDeposit) {
            $this->creditBalance($user, $redemption);
        }
    }

    private function creditBalance(User $user, PromoCodeRedemption $redemption): void
    {
        $this->transactions->createForUser($user, [
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => $redemption->value,
            'is_approved' => true,
            'description' => "Бонус по промокоду {$redemption->code}",
            'extra_data' => ['promo_code_redemption_id' => $redemption->id],
        ]);
    }
}
