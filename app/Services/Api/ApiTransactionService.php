<?php

namespace App\Services\Api;

use App\DTOs\Transaction\ApiDepositTransactionData;
use App\Models\TransactionType;
use App\Models\User;
use App\Repositories\InvoiceRepository;
use App\Repositories\TransactionRepository;
use App\Services\Payments\YooKassaPaymentService;
use App\Services\SubscriptionService;
use Carbon\Carbon;

class ApiTransactionService
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly TransactionRepository $transactions,
        private readonly SubscriptionService $subscriptionService,
        private readonly YooKassaPaymentService $yooKassaPaymentService,
    ) {}

    public function purchaseSubscription(User $user, ApiDepositTransactionData $data): array
    {
        $quote = $this->subscriptionService->buildPurchaseQuote($user, $data->month);

        if ($quote['deposit_amount'] <= 0) {
            $subscription = $this->subscriptionService->activatePackageForUser(
                user: $user,
                months: $data->month,
                packagePrice: (float) $quote['package_price'],
            );

            $formattedEndDate = Carbon::parse($subscription->end_date)->format('d.m.Y');

            return [
                'status' => 'activated',
                'message' => "Подписка активирована до $formattedEndDate.",
                'end_date' => $subscription->end_date,
                'formatted_end_date' => $formattedEndDate,
            ];
        }

        $description = $this->buildPaymentDescription($user, $data->month);
        $transaction = $this->transactions->createForUser($user, [
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => $quote['deposit_amount'],
            'is_approved' => false,
            'description' => 'YooKassa',
            'extra_data' => [
                'subscription_months' => $data->month,
                'base_month_price' => $quote['base_month_price'],
                'discount_percent' => $quote['discount_percent'],
                'package_full_price' => $quote['package_full_price'],
                'package_price' => $quote['package_price'],
                'balance_before' => $quote['balance_before'],
                'deposit_amount' => $quote['deposit_amount'],
            ],
        ]);

        $payment = $this->yooKassaPaymentService->createPayment(
            amount: (float) $transaction->amount,
            description: $description,
            metadata: [
                'user_id' => (string) $user->id,
                'transaction_id' => (string) $transaction->id,
                'subscription_months' => (string) $data->month,
            ],
            returnUrl: $data->returnUrl,
        );

        $invoice = $this->invoices->create([
            'user_id' => $user->id,
            'provider' => 'yookassa',
            'provider_payment_id' => (string) $payment['id'],
            'status' => (string) $payment['status'],
            'paid' => (bool) $payment['paid'],
            'amount' => (float) data_get($payment, 'amount.value', 0),
            'currency' => (string) data_get($payment, 'amount.currency', 'RUB'),
            'description' => (string) ($payment['description'] ?? $description),
            'confirmation_url' => data_get($payment, 'confirmation.confirmation_url'),
            'payload' => $payment['raw'] ?? $payment,
            'history' => [[
                'type' => 'payment.created',
                'status' => (string) $payment['status'],
                'paid' => (bool) $payment['paid'],
                'amount' => [
                    'value' => (string) data_get($payment, 'amount.value', '0'),
                    'currency' => (string) data_get($payment, 'amount.currency', 'RUB'),
                ],
                'occurred_at' => $payment['created_at'] ?? now()->toAtomString(),
                'payload' => $payment['raw'] ?? $payment,
            ]],
        ]);

        $this->transactions->update($transaction, [
            'invoice_id' => $invoice->id,
        ]);

        return [
            'status' => 'deposit_required',
            'message' => "Для активации подписки нужно оплатить {$transaction->amount} через YooKassa.",
            'deposit_amount' => (float) $transaction->amount,
            'transaction_id' => $transaction->id,
            'invoice_id' => $invoice->id,
            'payment_id' => $invoice->provider_payment_id,
            'payment_status' => $invoice->status,
            'confirmation_url' => $invoice->confirmation_url,
        ];
    }

    private function buildPaymentDescription(User $user, int $months): string
    {
        return "Подписка {$months} мес. для {$user->telegram}";
    }
}
