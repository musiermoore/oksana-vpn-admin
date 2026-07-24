<?php

declare(strict_types=1);

namespace App\Services\Crud;

use App\DTOs\Transaction\TransactionData;
use App\Enums\SubscriptionPurchaseType;
use App\Events\TransactionApproved;
use App\Jobs\SendTelegramMessageJob;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Repositories\TransactionRepository;
use Carbon\Carbon;

class TransactionCrudService
{
    public function __construct(
        private readonly TransactionRepository $transactions,
    ) {}

    public function create(TransactionData $data): Transaction
    {
        return $this->transactions->create($this->normalizeAttributes($data));
    }

    public function update(Transaction $transaction, TransactionData $data): Transaction
    {
        return $this->transactions->update($transaction, $this->normalizeAttributes($data));
    }

    public function delete(Transaction $transaction): void
    {
        $this->transactions->delete($transaction);
    }

    public function approve(Transaction $transaction): Transaction
    {
        if ($transaction->is_approved) {
            return $transaction->refresh();
        }

        $transaction = $this->transactions->update($transaction, ['is_approved' => true]);

        event(new TransactionApproved($transaction));

        $transaction = $transaction->refresh();

        $this->sendTelegramMessage([
            'chat_id' => $transaction->user->telegram_id,
            'text' => $this->buildApprovalTelegramMessage($transaction),
        ]);

        return $transaction;
    }

    public function decline(Transaction $transaction): void
    {
        $amount = $transaction->amount;
        $telegramId = $transaction->user->telegram_id;

        $this->transactions->delete($transaction);

        $this->sendTelegramMessage([
            'chat_id' => $telegramId,
            'text' => "Пополнение баланса на $amount отклонено",
        ]);
    }

    public function getApprovalTelegramMessage(Transaction $transaction): string
    {
        return $this->buildApprovalTelegramMessage($transaction->refresh());
    }

    private function normalizeAttributes(TransactionData $data): array
    {
        $attributes = $data->toArray();
        $attributes['amount'] = $this->normalizeAmount(
            typeId: (int) $attributes['type_id'],
            amount: (float) $attributes['amount'],
        );

        return $attributes;
    }

    private function normalizeAmount(int $typeId, float $amount): float
    {
        $normalizedAmount = abs($amount);
        $typeSlug = TransactionType::query()
            ->whereKey($typeId)
            ->value('slug');

        if (in_array($typeSlug, [
            TransactionType::SLUG_SUBSCRIPTION,
            TransactionType::SLUG_EXTRA_PAYMENT,
        ], true)) {
            return -$normalizedAmount;
        }

        return $normalizedAmount;
    }

    private function buildApprovalTelegramMessage(Transaction $transaction): string
    {
        $extraData = $transaction->extra_data ?? [];
        $giftCode = (string) data_get($extraData, 'gift_code', '');

        if (SubscriptionPurchaseType::tryFrom((string) data_get($extraData, 'purchase_type'))?->isGift() && $giftCode !== '') {
            $months = (int) data_get($extraData, 'subscription_months', 0);
            $duration = $months > 0 ? " на {$months} мес." : '';

            return "Подарочный код{$duration} готов: {$giftCode}.\nПередайте его получателю для активации в mini-app.";
        }

        $subscriptionEndDate = data_get($extraData, 'subscription_end_date');

        if (($extraData['package_activation_processed'] ?? false) === true && is_string($subscriptionEndDate) && $subscriptionEndDate !== '') {
            $subscriptionStartDate = (string) data_get($extraData, 'subscription_start_date', '');
            $formattedEndDate = Carbon::parse($subscriptionEndDate)->format('d.m.Y');
            $action = $subscriptionStartDate !== '' && Carbon::parse($subscriptionStartDate)->toDateString() !== today()->toDateString()
                ? 'продлена'
                : 'активирована';

            return "Подписка успешно {$action} до {$formattedEndDate}.";
        }

        return $transaction->approval_message_text;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendTelegramMessage(array $payload): void
    {
        SendTelegramMessageJob::dispatch($payload)->afterCommit();
    }
}
