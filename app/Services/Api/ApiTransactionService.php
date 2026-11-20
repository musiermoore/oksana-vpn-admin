<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\DTOs\PromoCode\PromoCodeQuote;
use App\DTOs\Transaction\ApiDepositTransactionData;
use App\Enums\SubscriptionPurchaseType;
use App\Jobs\DispatchDefaultConfigsForUserJob;
use App\Jobs\ReconcileUserAccessStateJob;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\UserSubscription;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Services\Payments\SubscriptionPaymentService;
use App\Services\PromoCodeService;
use App\Services\SubscriptionCodeService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

class ApiTransactionService
{
    public function __construct(
        private readonly TransactionRepository $transactions,
        private readonly SubscriptionService $subscriptionService,
        private readonly SubscriptionCodeService $subscriptionCodes,
        private readonly SubscriptionPaymentService $payments,
        private readonly PromoCodeService $promoCodes,
        private readonly UserRepository $users,
    ) {}

    public function purchaseSubscription(User $user, ApiDepositTransactionData $data): array
    {
        if ($data->promoCode !== null && ($data->month === 0 || $data->purchaseType->isGift())) {
            throw new DomainException('Промокод можно применить при покупке личной подписки.');
        }

        if ($data->purchaseType->isGift()) {
            return $this->purchaseGiftSubscriptionCode($user, $data);
        }

        if ($data->month === 0) {
            return $this->activateTrial($user);
        }

        $purchase = DB::transaction(fn () => $this->preparePurchase($this->users->lockById($user->id), $data));

        if ($purchase instanceof UserSubscription) {
            return $this->activationResponse($purchase->end_date, false);
        }

        try {
            return $this->createSubscriptionPayment($user, $data, $purchase);
        } catch (Throwable $exception) {
            $this->promoCodes->cancelPendingPayment($purchase);

            throw $exception;
        }
    }

    public function quotePromoCode(User $user, int $months, string $code): PromoCodeQuote
    {
        $quote = $this->subscriptionService->buildPurchaseQuote($user, $months);

        return $this->promoCodes->quote($user, $code, $quote['package_price'], $quote['balance_before']);
    }

    private function preparePurchase(?User $user, ApiDepositTransactionData $data): UserSubscription|Transaction
    {
        if ($user === null) {
            throw new DomainException('Пользователь не найден.');
        }

        $quote = $this->subscriptionService->buildPurchaseQuote($user, $data->month);
        $promo = $data->promoCode === null ? null : $this->promoCodes->quote(
            $user, $data->promoCode, $quote['package_price'], $quote['balance_before'],
        );

        if ($promo !== null) {
            $quote['package_price'] = $promo->packagePrice;
            $quote['deposit_amount'] = $promo->payableNow;
            $quote['promo_code_id'] = $promo->promoCode->id;
        }

        if ($quote['deposit_amount'] > 0) {
            $payment = $this->createSubscriptionTransaction($user, $data->month, $quote);

            if ($promo !== null) {
                $this->promoCodes->reserve($promo, $user, $payment);
            }

            return $payment;
        }

        return $this->activateFromBalance($user, $data->month, $quote, $promo);
    }

    private function activateTrial(User $user): array
    {
        $subscription = $this->subscriptionService->activateTrialForUser($user);
        $user->load('activeSubscription');

        if ($user->hasActiveSubscription()) {
            DispatchDefaultConfigsForUserJob::dispatch($user->id)->afterCommit();
            ReconcileUserAccessStateJob::dispatch($user->id)->afterCommit();
        }

        return $this->activationResponse($subscription->end_date, true);
    }

    private function activateFromBalance(User $user, int $months, array $quote, ?PromoCodeQuote $promo): UserSubscription
    {
        $redemption = $promo === null ? null : $this->promoCodes->reserve($promo, $user);
        $subscription = $this->subscriptionService->activatePackageForUser(
            user: $user,
            months: $months,
            packagePrice: (float) $quote['package_price'],
            purchaseMeta: [
                'subscription_months' => $months,
                'referral_accumulated_discount_percent' => $quote['referral_accumulated_discount_percent'],
                'referral_permanent_discount_percent' => $quote['referral_permanent_discount_percent'],
                'referral_total_discount_percent' => $quote['referral_total_discount_percent'],
                'referral_discount_amount' => $quote['referral_discount_amount'],
            ],
            redemption: $redemption,
        );

        if ($redemption !== null && $user->hasActiveSubscription()) {
            DispatchDefaultConfigsForUserJob::dispatch($user->id)->afterCommit();
            ReconcileUserAccessStateJob::dispatch($user->id)->afterCommit();
        }

        return $subscription;
    }

    private function activationResponse(mixed $endDate, bool $trial): array
    {
        $formattedEndDate = Carbon::parse($endDate)->format('d.m.Y');
        $prefix = $trial ? 'Пробная подписка активирована' : 'Подписка активирована';

        return [
            'status' => 'activated',
            'message' => "$prefix до $formattedEndDate.",
            'end_date' => $endDate,
            'formatted_end_date' => $formattedEndDate,
        ];
    }

    private function createSubscriptionPayment(User $user, ApiDepositTransactionData $data, Transaction $transaction): array
    {
        $description = $this->buildPaymentDescription($user, $data->month);
        $invoice = $this->payments->initialize($user, $transaction, $data, $description);

        if ($invoice->status === 'canceled') {
            $this->promoCodes->cancelPendingPayment($transaction);
        }

        return [
            'status' => 'deposit_required',
            'message' => "Для активации подписки необходимо оплатить {$transaction->amount} ₽. Чтобы перейти к оплате нажмите на кнопку «Перейти к оплате картой / СБП».",
            'deposit_amount' => (float) $transaction->amount,
            'transaction_id' => $transaction->id,
            'invoice_id' => $invoice->id,
            'payment_id' => $invoice->provider_payment_id,
            'payment_status' => $invoice->status,
            'confirmation_url' => $invoice->confirmation_url,
        ];
    }

    private function createSubscriptionTransaction(User $user, int $months, array $quote): Transaction
    {
        return $this->transactions->createForUser($user, [
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => $quote['deposit_amount'],
            'is_approved' => false,
            'description' => 'YooKassa',
            'extra_data' => [
                'subscription_months' => $months,
                'base_month_price' => $quote['base_month_price'],
                'discount_percent' => $quote['discount_percent'],
                'package_full_price' => $quote['package_full_price'],
                'price_before_referral_discount' => $quote['price_before_referral_discount'],
                'package_price' => $quote['package_price'],
                'balance_before' => $quote['balance_before'],
                'deposit_amount' => $quote['deposit_amount'],
                'referral_accumulated_discount_percent' => $quote['referral_accumulated_discount_percent'],
                'referral_permanent_discount_percent' => $quote['referral_permanent_discount_percent'],
                'referral_total_discount_percent' => $quote['referral_total_discount_percent'],
                'referral_discount_amount' => $quote['referral_discount_amount'],
                ...(isset($quote['promo_code_id']) ? ['promo_code_id' => $quote['promo_code_id']] : []),
            ],
        ]);
    }

    public function updateTelegramMessageMetadata(
        User $user,
        int $transactionId,
        int $telegramChatId,
        int $telegramMessageId,
    ): bool {
        $transaction = $this->transactions->findForUser($user, $transactionId);

        if (! $transaction instanceof Transaction) {
            return false;
        }

        $this->transactions->update($transaction, [
            'telegram_chat_id' => $telegramChatId,
            'telegram_message_id' => $telegramMessageId,
        ]);

        return true;
    }

    private function buildPaymentDescription(User $user, int $months): string
    {
        return "Подписка {$months} мес. для {$user->telegram}";
    }

    private function purchaseGiftSubscriptionCode(User $user, ApiDepositTransactionData $data): array
    {
        if ($data->month === 0) {
            throw new DomainException('Пробную подписку нельзя отправить в подарок.');
        }

        $quote = $this->subscriptionCodes->buildPurchaseQuote($user, $data->month);

        if ($quote['deposit_amount'] <= 0) {
            $code = $this->subscriptionCodes->issueCodeForUser(
                buyer: $user,
                months: $data->month,
                packagePrice: (float) $quote['package_price'],
                purchaseMeta: [
                    'purchase_type' => SubscriptionPurchaseType::GIFT->value,
                    'subscription_months' => $data->month,
                ],
            );

            return [
                'status' => 'gift_code_created',
                'message' => 'Подарочный код создан. Передайте его получателю для активации в mini-app.',
                'code' => $code->code,
                'months' => $code->months,
                'days' => $code->days,
            ];
        }

        $description = "Подарочный код {$data->month} мес. от {$user->telegram}";
        $transaction = $this->transactions->createForUser($user, [
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => $quote['deposit_amount'],
            'is_approved' => false,
            'description' => 'YooKassa',
            'extra_data' => [
                'purchase_type' => SubscriptionPurchaseType::GIFT->value,
                'subscription_months' => $data->month,
                'base_month_price' => $quote['base_month_price'],
                'discount_percent' => $quote['discount_percent'],
                'package_full_price' => $quote['package_full_price'],
                'package_price' => $quote['package_price'],
                'balance_before' => $quote['balance_before'],
                'deposit_amount' => $quote['deposit_amount'],
            ],
        ]);

        $invoice = $this->payments->initialize($user, $transaction, $data, $description);

        return [
            'status' => 'deposit_required',
            'message' => "Для получения подарочного кода необходимо оплатить {$transaction->amount} ₽. После оплаты код появится в mini-app и придёт сообщением.",
            'deposit_amount' => (float) $transaction->amount,
            'transaction_id' => $transaction->id,
            'invoice_id' => $invoice->id,
            'payment_id' => $invoice->provider_payment_id,
            'payment_status' => $invoice->status,
            'confirmation_url' => $invoice->confirmation_url,
        ];
    }
}
