<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Services\Crud\TransactionCrudService;
use App\Services\TelegramDevChatService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class YooKassaWebhookService
{
    public function __construct(
        private readonly TransactionCrudService $transactionService,
        private readonly TelegramDevChatService $devChat,
    ) {}

    public function handle(array $payload): void
    {
        $event = (string) data_get($payload, 'event', '');
        $paymentId = (string) data_get($payload, 'object.id', '');

        if ($event === '' || $paymentId === '') {
            return;
        }

        DB::transaction(function () use ($payload, $event, $paymentId): void {
            $invoice = Invoice::query()
                ->with(['transactions.user'])
                ->where('provider', 'yookassa')
                ->where('provider_payment_id', $paymentId)
                ->lockForUpdate()
                ->first();

            if (! $invoice) {
                $this->devChat->send("YooKassa webhook: не найден invoice для платежа {$paymentId} ({$event}).");

                return;
            }

            $wasPaid = (bool) $invoice->paid;

            $this->syncInvoice($invoice, $payload);

            $transaction = $invoice->transactions->first();

            if ($event === 'payment.succeeded' && $transaction !== null) {
                $this->transactionService->approve($transaction);
            }

            if ($event === 'payment.canceled' && ($wasPaid || (bool) data_get($payload, 'object.paid', false))) {
                $userName = $transaction?->user?->full_name ?? "user_id={$invoice->user_id}";
                $amount = (string) data_get($payload, 'object.amount.value', $invoice->amount);

                $this->devChat->send(
                    "YooKassa: платеж {$paymentId} отменён после оплаты. Пользователь: {$userName}. Сумма: {$amount} RUB."
                );
            }
        });
    }

    private function syncInvoice(Invoice $invoice, array $payload): void
    {
        $status = (string) data_get($payload, 'object.status', $invoice->status);
        $paid = (bool) data_get($payload, 'object.paid', $invoice->paid);
        $history = $invoice->history ?? [];
        $history[] = [
            'type' => (string) data_get($payload, 'event', 'payment.updated'),
            'status' => $status,
            'paid' => $paid,
            'amount' => [
                'value' => (string) data_get($payload, 'object.amount.value', $invoice->amount),
                'currency' => (string) data_get($payload, 'object.amount.currency', $invoice->currency),
            ],
            'occurred_at' => (string) data_get($payload, 'object.created_at', now()->toAtomString()),
            'payload' => $payload,
        ];

        $invoice->update([
            'status' => $status,
            'paid' => $paid,
            'amount' => (float) data_get($payload, 'object.amount.value', $invoice->amount),
            'currency' => (string) data_get($payload, 'object.amount.currency', $invoice->currency),
            'description' => data_get($payload, 'object.description', $invoice->description),
            'confirmation_url' => data_get($payload, 'object.confirmation.confirmation_url', $invoice->confirmation_url),
            'payload' => $payload,
            'history' => $history,
            'paid_at' => $paid
                ? Carbon::parse((string) data_get($payload, 'object.created_at', now()->toAtomString()))
                : $invoice->paid_at,
            'canceled_at' => $status === 'canceled'
                ? Carbon::parse((string) data_get($payload, 'object.created_at', now()->toAtomString()))
                : null,
        ]);
    }
}
