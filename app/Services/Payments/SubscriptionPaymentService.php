<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\DTOs\Transaction\ApiDepositTransactionData;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\InvoiceRepository;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\DB;

class SubscriptionPaymentService
{
    public function __construct(
        private readonly YooKassaPaymentService $yooKassa,
        private readonly InvoiceRepository $invoices,
        private readonly TransactionRepository $transactions,
    ) {}

    public function initialize(User $user, Transaction $transaction, ApiDepositTransactionData $data, string $description): Invoice
    {
        $metadata = [
            'user_id' => (string) $user->id,
            'transaction_id' => (string) $transaction->id,
            'subscription_months' => (string) $data->month,
        ];

        if ($data->purchaseType->isGift()) {
            $metadata['purchase_type'] = $data->purchaseType->value;
        }

        $payment = $this->yooKassa->createPayment(
            amount: (float) $transaction->amount,
            description: $description,
            metadata: $metadata,
            returnUrl: $data->returnUrl,
        );

        return DB::transaction(function () use ($user, $transaction, $payment, $description): Invoice {
            $invoice = $this->createInvoice($user, $payment, $description);
            $this->transactions->update($transaction, ['invoice_id' => $invoice->id]);

            return $invoice;
        });
    }

    private function createInvoice(User $user, array $payment, string $description): Invoice
    {
        return $this->invoices->create([
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
    }
}
